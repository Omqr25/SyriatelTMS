"""Run a focused Laravel worker matrix on a newly created private SQLite database."""
import argparse
import json
import os
from pathlib import Path
import sqlite3
import subprocess
import tempfile
import time


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--php', required=True, type=Path)
    parser.add_argument('--autoload', required=True, type=Path)
    parser.add_argument('--output', required=True, type=Path, help='Parent directory for fresh test data')
    args = parser.parse_args()
    assert args.autoload.is_file()
    args.output.mkdir(parents=True, exist_ok=True)
    root = Path(tempfile.mkdtemp(prefix='certificate-worker-probe-',dir=args.output.resolve()))
    print(root, flush=True)
    (root/'.isolated-worker-probe').touch()
    (root/'probe.sqlite').touch()
    (root/'private').mkdir(mode=0o700)
    (root/'bootstrap/cache').mkdir(parents=True)
    log = root/'events.jsonl'
    script = Path(__file__).resolve().with_name('probe.php')
    env = {key: value for key,value in os.environ.items()
           if not key.startswith(('APP_', 'DB_', 'QUEUE_', 'CACHE_', 'LOG_', 'PROBE_'))}
    env['PROBE_VENDOR_AUTOLOAD'] = str(args.autoload.resolve())
    processes = []
    summary = {}

    def start(*command, no_signals=False):
        variables = env | {'PROBE_SIMULATE_NO_PCNTL':'1'} if no_signals else env
        process = subprocess.Popen([str(args.php.resolve()), '-d', 'memory_limit=128M',str(script),*command],
            cwd=root,env=variables,stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True)
        processes.append(process)
        return process

    def finish(process, allow_nonzero=False):
        output,_ = process.communicate(timeout=9)
        with (root/'commands.log').open('a') as file:
            file.write(f'{process.pid} {process.returncode} {process.args[4:]}\n{output}\n')
        if not allow_nonzero:
            assert process.returncode == 0, output
        return process.returncode

    def run(*command):
        return finish(start(*command))

    def events(name, **fields):
        if not log.exists():
            return []
        return [entry for line in log.read_text().splitlines(keepends=True) if line.endswith('\n')
                if (entry := json.loads(line))['event']==name and
                all(entry.get(key)==value for key,value in fields.items())]

    def wait_for(test, timeout=8, interval=.025):
        until = time.monotonic()+timeout
        while time.monotonic()<until:
            result = test()
            if result:
                return result
            time.sleep(interval)
        raise AssertionError('Timed out waiting for expected checkpoint')

    def query(sql, params=()):
        with sqlite3.connect(root/'probe.sqlite',timeout=4) as db:
            return db.execute(sql,params).fetchall()

    def result(key):
        return query('SELECT state,attempt,path FROM probe_results WHERE key=?',(key,))[0]

    def verify_output(key, attempt):
        state,count,path = result(key)
        assert (state,count)==('ready',attempt), (key,state,count,path)
        assert (root/path).read_text()==f'synthetic certificate {key}, attempt {attempt}\n'
        assert len(list((root/'private').glob(f'{key}-*')))==1
        assert len(events('published',key=key))==1

    def enqueue(key, mode='normal', lane='certificates'):
        run('probe:enqueue',lane,key,mode)

    def ticks_until(key):
        def complete():
            run('schedule:run')
            return result(key)[0]=='ready'
        wait_for(complete, interval=.2)

    try:
        run('probe:init')
        run('schedule:run')
        assert not events('worker_start')
        for key in ('first','first','second'):
            enqueue(key)
        for lane in ('core','export'):
            enqueue(lane+'-work',lane=lane)
        # Decoy on the same table: the certificate queue must not pop it.
        query("INSERT INTO certificate_probe_jobs (queue,payload,attempts,available_at,created_at) VALUES ('other','{}',0,0,0)")
        companions = [start('probe:drain',lane) for lane in ('core','export')]
        keep_open = root/'hold-shutdown'
        keep_open.touch()
        launch_started = time.time()
        launchers = [start('schedule:run') for _ in range(5)]
        checkpoint = wait_for(lambda:events('shutdown_window'))[0]
        lock_denials = events('already_running',lane='certificates')
        assert lock_denials
        enqueue('late')
        keep_open.unlink()
        for process in launchers+companions:
            finish(process)
        assert result('late')[:2]==('pending',0)
        verify_output('first',1)
        verify_output('second',1)
        claims = events('claimed')
        assert [x['key'] for x in claims]==['first','second']
        assert events('published',key='first')[0]['at'] <= claims[1]['at']
        assert len(events('duplicate_ignored',key='first'))==1
        assert len(events('worker_start',lane='certificates'))==1
        assert len(events('worker_stop',lane='certificates'))==1
        for lane,queue in [('core','default'),('export','applicant-exports')]:
            partner_start = events('job_enter',lane=lane,queue=queue)[0]
            partner_stop = events('companion_done',lane=lane,queue=queue)[0]
            assert partner_start['at'] <= events('published',key='second')[0]['at'] and partner_stop['at'] >= claims[0]['at']
        assert query('SELECT queue FROM certificate_probe_jobs WHERE queue=?',('other',))==[('other',)]
        ticks_until('late')
        verify_output('late',1)
        summary['single_worker_and_isolation'] = {
            'certificate_launches':5,'losing_locks':len(lock_denials),
            'first_tick_to_worker_ms':round((events('worker_start',lane='certificates')[0]['at']-launch_started)*1000,1),
            'late_enqueue_checkpoint_pid':checkpoint['pid'],
        }

        enqueue('delayed','retry')
        run('schedule:run')
        assert result('delayed')[:2]==('pending',1)
        count = len(events('worker_start',lane='certificates'))
        active_after = query("SELECT available_at FROM certificate_probe_jobs WHERE queue='certificates'")[0][0]
        if int(time.time()) < active_after:
            run('schedule:run')
            assert len(events('worker_start',lane='certificates'))==count
        ticks_until('delayed')
        verify_output('delayed',2)
        start2=events('claimed',key='delayed')[1]['at']
        assert start2 >= active_after
        summary['delayed_retry']={'due_to_recovery_ms':round((start2-active_after)*1000,1)}

        enqueue('crashed','crash')
        crashed = start('schedule:run')
        candidate_event = wait_for(lambda:events('renamed_before_commit',key='crashed'))[0]
        assert result('crashed')==('running',1,None)
        assert (root/'private'/f"crashed-{candidate_event['fence']}.output").is_file()
        run('schedule:run')
        assert candidate_event['pid']==crashed.pid
        killed_at = time.time()
        crashed.kill()
        assert finish(crashed,allow_nonzero=True)!=0
        ticks_until('crashed')
        verify_output('crashed',2)
        run('probe:replay','crashed',candidate_event['fence'])
        verify_output('crashed',2)
        assert events('stale_publication_rejected',key='crashed')
        summary['crash_recovery']={'milliseconds_after_kill':round(
            (events('published',key='crashed')[0]['at']-killed_at)*1000,1)}

        enqueue('stalled','stall')
        stalled = start('schedule:run')
        claim = wait_for(lambda:events('claimed',key='stalled'))[0]
        assert finish(stalled,allow_nonzero=True)!=0
        signal = events('worker_timeout')[0]
        elapsed = signal['at']-claim['at']
        assert 1.7<elapsed<3, elapsed
        ticks_until('stalled')
        verify_output('stalled',2)
        summary['pcntl_timeout']={'claim_to_timeout_ms':round(elapsed*1000,1)}

        enqueue('no-signals','stall')
        limited = start('schedule:run',no_signals=True)
        wait_for(lambda:events('claimed',key='no-signals'))
        time.sleep(2.3)
        assert limited.poll() is None, 'Unexpected hard timeout with async signals disabled'
        limited.kill()
        assert finish(limited,allow_nonzero=True)!=0
        ticks_until('no-signals')
        verify_output('no-signals',2)
        summary['no_pcntl_simulation']='Laravel timeout did not stop the job. External kill allowed recovery.'

        before = len(events('worker_start',lane='certificates'))
        run('schedule:run')
        assert before==len(events('worker_start',lane='certificates'))
        assert all(process.poll() is not None for process in processes)
        assert query("SELECT queue FROM certificate_probe_jobs")==[('other',)]
        assert all(query(f'SELECT count(*) FROM {table}')[0][0]==0
                   for table in ('jobs','applicant_export_jobs'))
        summary['idle']={'running_probe_processes':0,'certificate_queue_depth':0,
            'peak_php_mib':max(event['peak_bytes'] for event in events('worker_stop',lane='certificates'))/1024**2}
        summary['result']='pass'
        (root/'summary.json').write_text(json.dumps(summary,indent=2)+'\n')
        print(json.dumps(summary,indent=2))
    finally:
        for process in processes:
            if process.poll() is None:
                process.kill()
                process.communicate(timeout=3)
        # Keep event logs and evidence only within the explicit scratch directory.


if __name__=='__main__':
    main()
