<?php
declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Events\JobTimedOut;
use Illuminate\Queue\Events\WorkerIdle;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Input\ArgvInput;

const RETRY_AFTER = 5;
const TIMEOUT = 2;
const LANES = [
    'certificates' => ['certificate_probe', 'certificate_probe_jobs', 'certificates'],
    'core' => ['database', 'jobs', 'default'],
    'export' => ['applicant_exports', 'applicant_export_jobs', 'applicant-exports'],
];

$root = realpath(getcwd());
if (!$root || !str_starts_with(basename($root), 'certificate-worker-probe-') ||
    !is_file($root . '/.isolated-worker-probe') || is_file($root . '/.env') ||
    !is_file($root . '/probe.sqlite') || is_link($root . '/probe.sqlite') ||
    is_file($root . '/bootstrap/cache/config.php')) {
    fwrite(STDERR, "Requires a fresh, marked, isolated probe directory.\n");
    exit(2);
}
require getenv('PROBE_VENDOR_AUTOLOAD');

function traceEvent(string $event, array $data = []): void
{
    $line = json_encode(['at'=>microtime(true),'pid'=>getmypid(),'event'=>$event] + $data, JSON_THROW_ON_ERROR);
    file_put_contents('events.jsonl', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function drain(string $lane): int
{
    [$connection,$table,$queue] = LANES[$lane];
    // The draining process owns the lock itself. No expiry and no PID lock file.
    $handle = fopen($lane . '.lock', 'c');
    if (!$handle) {
        throw new RuntimeException('Lock path unavailable');
    }
    try {
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            traceEvent('already_running', ['lane'=>$lane]);
            return 0;
        }
        $now = time();
        $runnable = DB::table($table)->where('queue',$queue)->where(function ($query) use ($now) {
            $query->where(fn ($job) => $job->whereNull('reserved_at')->where('available_at','<=',$now))
                  ->orWhere('reserved_at','<=',$now - RETRY_AFTER);
        })->exists();
        if (!$runnable) {
            traceEvent('no_due_work', ['lane'=>$lane]);
            return 0;
        }
        traceEvent('worker_start', ['lane'=>$lane]);
        if ($lane === 'certificates') {
            Event::listen(JobTimedOut::class, fn () => traceEvent('worker_timeout'));
            Event::listen(WorkerIdle::class, function () {
                if (is_file('hold-shutdown')) {
                    traceEvent('shutdown_window');
                    $deadline = microtime(true) + 1;
                    while (is_file('hold-shutdown') && microtime(true) < $deadline) {
                        usleep(10000);
                    }
                }
            });
        }
        $worker = app('queue.worker');
        if (getenv('PROBE_SIMULATE_NO_PCNTL') === '1') {
            $worker = new class(app('queue'),app('events'),app(Illuminate\Contracts\Debug\ExceptionHandler::class),fn () => false) extends Worker {
                protected function supportsAsyncSignals()
                {
                    return false;
                }
            };
        }
        $status = $worker->daemon($connection,$queue,new WorkerOptions(
            memory:96,timeout:TIMEOUT,sleep:0,maxTries:3,stopWhenEmpty:true,maxJobs:12,maxTime:15,
        ));
        traceEvent('worker_stop', ['lane'=>$lane,'status'=>$status,'peak_bytes'=>memory_get_peak_usage(true)]);
        return $status;
    } finally {
        fclose($handle);
    }
}

/** Each attempted output is private and immutable. Only a committed pointer makes it visible. */
function publish(string $key, int $attempt, string $fence, bool $pauseBeforeCommit = false): bool
{
    $base = 'private/' . $key . '-' . $fence;
    $stage = $base . '.tmp';
    $candidate = $base . '.output';
    $handle = fopen($stage,'xb');
    if (!$handle) {
        throw new RuntimeException('Stage unavailable');
    }
    try {
        $contents = "synthetic certificate {$key}, attempt {$attempt}\n";
        if (fwrite($handle,$contents) !== strlen($contents) || !fflush($handle)) {
            throw new RuntimeException('Incomplete stage');
        }
    } finally {
        fclose($handle);
    }
    if (file_exists($candidate) || !rename($stage,$candidate)) {
        throw new RuntimeException('Same-filesystem rename failed');
    }
    if ($pauseBeforeCommit) {
        traceEvent('renamed_before_commit',['key'=>$key,'attempt'=>$attempt,'fence'=>$fence]);
        sleep(20);
    }
    $accepted = DB::table('probe_results')->where('key',$key)->where('state','running')
        ->where('fence',$fence)->update(['state'=>'ready','path'=>$candidate]) === 1;
    if (!$accepted) {
        unlink($candidate);
        traceEvent('stale_publication_rejected',['key'=>$key]);
        return false;
    }
    traceEvent('published',['key'=>$key,'attempt'=>$attempt,'path'=>$candidate]);
    // Safe here only because the certificate worker is serialized, at this test scale.
    foreach (glob('private/' . $key . '-*') as $path) {
        if ($path !== $candidate) {
            unlink($path);
        }
    }
    return true;
}

final class ProbeJob implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(public string $lane,public string $key,public string $mode)
    {
    }

    public function handle(): void
    {
        $queue = $this->job->getQueue();
        traceEvent('job_enter',['lane'=>$this->lane,'queue'=>$queue,'key'=>$this->key]);
        if ($this->lane !== 'certificates') {
            usleep(450000);
            traceEvent('companion_done',['lane'=>$this->lane,'queue'=>$queue,'key'=>$this->key]);
            return;
        }
        $fence = bin2hex(random_bytes(12));
        $now = time();
        $row = DB::table('probe_results')->where('key',$this->key);
        $claimed = (clone $row)->where('attempt','<',3)
            ->where(fn ($status) => $status->where('state','pending')
                ->orWhere(fn ($running) => $running->where('state','running')->where('lease_until','<=',$now)))
            ->increment('attempt',1,['state'=>'running','lease_until'=>$now+RETRY_AFTER,'fence'=>$fence]);
        if ($claimed !== 1) {
            traceEvent('duplicate_ignored',['key'=>$this->key]);
            return;
        }
        $attempt = (int) $row->value('attempt');
        traceEvent('claimed',['key'=>$this->key,'attempt'=>$attempt,'fence'=>$fence]);
        if ($attempt === 1 && $this->mode === 'retry') {
            $row->where('fence',$fence)->update(['state'=>'pending','lease_until'=>null]);
            $this->release(3);
            traceEvent('retry_released',['key'=>$this->key]);
            return;
        }
        if ($attempt === 1 && $this->mode === 'stall') {
            sleep(20);
        }
        usleep(180000);
        publish($this->key,$attempt,$fence,$attempt === 1 && $this->mode === 'crash');
    }
}

$app = Application::configure(basePath:$root)
    ->withExceptions()
    ->withSchedule(fn (Schedule $schedule) => $schedule->call(fn () => drain('certificates'))->everyMinute())
    ->create();
$app->afterBootstrapping(LoadConfiguration::class, function (Application $app) use ($root) {
    $app['config']->set([
        'app.env'=>'testing','app.debug'=>false,'app.timezone'=>'UTC',
        'cache.default'=>'array','queue.default'=>'database','queue.failed.driver'=>'null',
        'database.default'=>'probe',
        'database.connections.probe'=>[
            'driver'=>'sqlite','database'=>$root . '/probe.sqlite','prefix'=>'',
            'busy_timeout'=>4000,'journal_mode'=>'wal','transaction_mode'=>'IMMEDIATE',
        ],
        'logging.default'=>'stderr',
    ]);
    foreach (LANES as [$connection,$table,$queue]) {
        $app['config']->set('queue.connections.' . $connection,[
            'driver'=>'database','connection'=>'probe','table'=>$table,
            'queue'=>$queue,'retry_after'=>RETRY_AFTER,'after_commit'=>false,
        ]);
    }
});
$app->booted(function () {
    Artisan::command('probe:init', function () {
        foreach (LANES as [,$table]) {
            Schema::create($table,function ($schema) {
                $schema->increments('id');
                $schema->string('queue')->index();
                $schema->longText('payload');
                $schema->unsignedTinyInteger('attempts');
                $schema->integer('reserved_at')->nullable();
                $schema->integer('available_at');
                $schema->integer('created_at');
            });
        }
        Schema::create('probe_results',function ($schema) {
            $schema->string('key')->primary();
            $schema->string('state')->default('pending');
            $schema->integer('attempt')->default(0);
            $schema->integer('lease_until')->nullable();
            $schema->string('fence')->nullable();
            $schema->string('path')->nullable();
        });
    });
    Artisan::command('probe:enqueue {lane} {key} {mode=normal} {delay=0}',function () {
        $lane = $this->argument('lane');
        $key = $this->argument('key');
        if (!isset(LANES[$lane]) || !preg_match('/^[a-z0-9-]+$/D',$key)) {
            return 2;
        }
        [$connection,,$queue] = LANES[$lane];
        DB::transaction(function () use ($connection,$queue,$lane,$key) {
            if ($lane === 'certificates') {
                DB::table('probe_results')->insertOrIgnore(['key'=>$key]);
            }
            $job = new ProbeJob($lane,$key,$this->argument('mode'));
            $delay = (int) $this->argument('delay');
            Queue::connection($connection)->later($delay,$job,'',$queue);
        });
        traceEvent('enqueued',['lane'=>$lane,'key'=>$key]);
        return 0;
    });
    Artisan::command('probe:drain {lane}',fn () => drain($this->argument('lane')));
    Artisan::command('probe:replay {key} {fence}',function () {
        if (!preg_match('/^[a-z0-9-]+$/D',$this->argument('key')) ||
            !preg_match('/^[a-f0-9]{24}$/D',$this->argument('fence'))) {
            return 2;
        }
        return publish($this->argument('key'),1,$this->argument('fence')) ? 1 : 0;
    });
});
try {
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    exit($app->handleCommand(new ArgvInput));
} catch (Throwable $exception) {
    fwrite(STDERR, (string) $exception . PHP_EOL);
    exit(1);
}
