<?php

namespace App\Services\Training\ApplicantExport;

use App\Jobs\ProcessApplicantExport;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ExportQueue
{
    public function __construct(private ExportFiles $files) {}

    public function reconcile(): void
    {
        foreach (ExportStore::tasks()->whereNotNull('owner')->where('lease_until', '<=', now())->limit(25)->get() as $task) {
            // Windows can lack PCNTL timeouts. A live process holding the file lock is never replaced.
            $lock = $this->files->lock($task->export_id.'-'.$task->id, true);
            if (! $lock) continue;
            try {
                $this->finishFailed($task, 'worker_interrupted');
            } finally {
                fclose($lock);
            }
        }
        foreach (ExportStore::exports()->where('expires_at', '<=', now())
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('applicant_export_tasks')
                ->whereColumn('export_id', 'applicant_exports.id')->where('kind', 'prune'))
            ->limit(10)->pluck('id') as $id) {
            ExportStore::tasks()->insertOrIgnore([
                'export_id' => $id, 'kind' => 'prune', 'part' => 0, 'state' => 'queued', 'due_at' => now(),
            ]);
        }
        ExportStore::tasks()->where('kind', 'prune')->where('state', 'failed')->where('due_at', '<=', now()->subHour())
            ->update(['state' => 'queued', 'attempts' => 0, 'dispatch_after' => null]);
        $this->dispatch();
    }

    public function dispatch(?string $id = null): void
    {
        // Reconciliation is bounded and only enqueues. No ZIP or document I/O runs in the scheduler.
        $jobTable = config('queue.connections.applicant_exports.table');
        $tasks = ExportStore::tasks()->where('state', 'queued')->where('due_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('dispatch_after')->orWhere('dispatch_after', '<=', now()))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from($jobTable)
                ->whereColumn($jobTable.'.id', 'applicant_export_tasks.queue_job_id')->where('queue', 'applicant-exports'))
            ->when($id, fn ($query) => $query->where('export_id', $id))->orderBy('id')->limit(8)->pluck('id');
        foreach ($tasks as $taskId) {
            DB::transaction(function () use ($taskId) {
                $changed = ExportStore::tasks()->where('id', $taskId)->where('state', 'queued')
                    ->where(fn ($query) => $query->whereNull('dispatch_after')->orWhere('dispatch_after', '<=', now()))
                    ->update(['dispatch_after' => now()->addMinutes(15)]);
                if ($changed) {
                    $jobId = Bus::dispatch(new ProcessApplicantExport($taskId));
                    if (is_numeric($jobId)) ExportStore::tasks()->where('id', $taskId)->update(['queue_job_id' => $jobId]);
                }
            }, 3);
        }
    }

    public function process(int $taskId): void
    {
        $candidate = ExportStore::tasks()->find($taskId);
        if (! $candidate || $candidate->state !== 'queued') return;

        $taskLock = $this->files->lock($candidate->export_id.'-'.$taskId, true);
        if (! $taskLock) {
            $this->defer($candidate->id);

            return;
        }
        $exportLock = null;
        $temporary = null;
        $task = null;
        $publishing = false;
        $pruned = false;
        try {
            $exportLock = $this->files->lock($candidate->export_id, $candidate->kind === 'prune');
            if (! $exportLock) {
                $this->defer($candidate->id);

                return;
            }
            $directory = $this->files->directory($candidate->export_id);
            $free = disk_free_space($this->files->directory('.locks'));
            $task = $this->claim($candidate, $free === false ? 0 : $free);
            if (! $task) return;

            if ($task->kind === 'prune') {
                $pruned = $this->prune($task);
                return;
            }

            File::ensureDirectoryExists($directory, 0700);
            // A prior worker may have died after writing but before recording the result.
            foreach (File::glob("$directory/attempt-$task->id-*") as $orphan) {
                if (! File::delete($orphan)) throw new RuntimeException('temporary_cleanup_failed');
            }
            $temporary = "$directory/attempt-$task->id-$task->owner";
            $export = ExportStore::exports()->find($task->export_id);
            $deadline = microtime(true) + max(1, config('applicant_exports.timeout'));
            $progress = function (int $count) use ($task, $deadline): void {
                if (microtime(true) >= $deadline) throw new RuntimeException('processing_timeout');
                if ($count % 20 === 0) $this->checkpoint($task, $count);
            };
            $plan = null;
            $warnings = null;
            match ($task->kind) {
                'capture' => $warnings = $this->files->capture($export, $progress),
                'excel' => $this->files->excel($export, $temporary, $progress),
                'plan' => $plan = $this->files->plan($export, $progress),
                'zip' => $this->files->zip($export, $task->part, $task->estimate > config('applicant_exports.part_bytes'), $temporary, $progress),
                default => throw new RuntimeException('unknown_task'),
            };
            $this->checkpoint($task, $export->applicant_count, true);

            $result = [];
            if (is_file($temporary)) {
                $size = filesize($temporary);
                $hash = hash_file('sha256', $temporary);
                if ($size === false || $hash === false || $size > $task->reservation) {
                    throw new RuntimeException('artifact_budget_exceeded');
                }
                // The path is attempt-specific. The atomic DB commit makes it visible only to its immutable artifact.
                $result = ['file' => basename($temporary), 'bytes' => $size, 'sha256' => $hash, 'reservation' => $size];
            }
            if (microtime(true) >= $deadline) throw new RuntimeException('processing_timeout');
            if (DB::transactionLevel() === 0) DB::reconnect();
            $publishing = true;
            if (! $this->publish($task, $result, $plan, $warnings) && is_file($temporary)) File::delete($temporary);
        } catch (Throwable $exception) {
            // An ambiguous commit may already have published the file. A retry cleans it only if still unpublished.
            $released = ! $publishing && $temporary !== null
                && (! is_file($temporary) || File::delete($temporary));
            if ($task) {
                $error = $exception instanceof RuntimeException && preg_match('/^[a-z_]+$/D', $exception->getMessage())
                    ? $exception->getMessage() : 'processing_failed';
                $this->finishFailed($task, $error, $released);
                Log::warning('Applicant export task unsuccessful', [
                    'task_id' => $task->id, 'reason' => $error, 'exception' => $exception::class,
                    'source' => basename($exception->getFile()).':'.$exception->getLine(),
                ]);
            } else {
                throw $exception;
            }
        } finally {
            if ($exportLock) fclose($exportLock);
            fclose($taskLock);
            if ($pruned) {
                $locks = $this->files->directory('.locks');
                File::delete(array_merge(["$locks/$candidate->export_id"], File::glob("$locks/$candidate->export_id-*")));
            }
        }

        $this->dispatch($candidate->export_id);
    }

    private function defer(int $id): void
    {
        ExportStore::tasks()->where('id', $id)->where('state', 'queued')
            ->update(['dispatch_after' => null, 'due_at' => now()->addSeconds(15)]);
    }

    private function claim(object $candidate, float $free): ?object
    {
        return DB::transaction(function () use ($candidate, $free) {
            DB::table('applicant_export_capacity')->where('id', 1)->lockForUpdate()->first();
            $export = ExportStore::exports()->where('id', $candidate->export_id)->lockForUpdate()->first();
            $task = ExportStore::tasks()->where('id', $candidate->id)->lockForUpdate()->first();
            if (! $export || ! $task || $task->state !== 'queued' || now()->lt($task->due_at)) return null;

            if ($task->kind !== 'prune' && (now()->gte($export->expires_at) || ! $export->operation)) {
                ExportStore::tasks()->where('id', $task->id)->update(['state' => 'cancelled']);
                ExportStore::settle($export->id);
                return null;
            }
            if (ExportStore::tasks()->whereNotNull('owner')->count() >= max(1, config('applicant_exports.concurrency'))) {
                $this->defer($task->id);
                return null;
            }

            $other = ExportStore::tasks()->where('id', '!=', $task->id);
            $required = $task->estimate;
            if ($required > 0 && ($required + (clone $other)->sum('reservation') > config('applicant_exports.storage_bytes')
                || $required + (clone $other)->whereNotNull('owner')->sum('reservation') + config('applicant_exports.min_free_bytes') > $free)) {
                ExportStore::tasks()->where('id', $task->id)->update(['state' => 'failed', 'error' => 'storage_exhausted']);
                ExportStore::settle($export->id);
                return null;
            }

            $owner = (string) Str::uuid();
            $attempts = $task->attempts + 1;
            ExportStore::tasks()->where('id', $task->id)->update([
                'state' => 'running', 'owner' => $owner, 'attempts' => $attempts,
                'lease_until' => now()->addSeconds(config('queue.connections.applicant_exports.retry_after')),
                'reservation' => $required, 'progress' => 0,
            ]);

            return (object) array_replace((array) $task, ['owner' => $owner, 'attempts' => $attempts, 'reservation' => $required]);
        }, 3);
    }

    private function checkpoint(object $task, int $count, bool $reconnect = false): void
    {
        // PHP can be busy with ZipArchive::close() and checksumming without any SQL traffic.
        if ($reconnect && DB::transactionLevel() === 0) DB::reconnect();
        $current = ExportStore::tasks()->where('id', $task->id)->where('owner', $task->owner)
            ->where('state', 'running')->exists();
        if (! $current || ! ExportStore::exports()->where('id', $task->export_id)->where('expires_at', '>', now())->exists()) {
            throw new RuntimeException('cancelled');
        }
        ExportStore::tasks()->where('id', $task->id)->where('owner', $task->owner)->where('progress', '<', $count)
            ->update(['progress' => $count]);
    }

    private function publish(object $task, array $result, ?array $plan, ?int $warnings): bool
    {
        return DB::transaction(function () use ($task, $result, $plan, $warnings) {
            $export = ExportStore::exports()->where('id', $task->export_id)->lockForUpdate()->first();
            $current = ExportStore::tasks()->where('id', $task->id)->where('owner', $task->owner)->lockForUpdate()->first();
            if (! $current || ! $export) return false;
            $ready = $current->state === 'running' && now()->lt($export->expires_at);
            if ($ready && $warnings !== null) {
                ExportStore::exports()->where('id', $export->id)->update(['document_warnings' => $warnings]);
            }
            if ($ready && $plan !== null) {
                foreach ($plan as $part => $estimate) ExportStore::task($export->id, 'zip', $part, $estimate);
                if (! $plan) ExportStore::exports()->where('id', $export->id)->update(['documents_state' => 'empty']);
            }
            ExportStore::tasks()->where('id', $task->id)->update(($ready ? $result : []) + [
                'state' => $ready ? 'ready' : 'cancelled', 'owner' => null, 'lease_until' => null,
                'reservation' => 0, 'error' => null,
            ]);
            ExportStore::settle($task->export_id);
            return $ready;
        }, 3);
    }

    private function finishFailed(object $task, string $error, bool $released = false): void
    {
        DB::transaction(function () use ($task, $error, $released) {
            $export = ExportStore::exports()->where('id', $task->export_id)->lockForUpdate()->first();
            $current = ExportStore::tasks()->where('id', $task->id)->where('owner', $task->owner)->lockForUpdate()->first();
            if (! $export || ! $current) return;
            $cancelled = $current->state === 'cancelled' || ($task->kind !== 'prune' && now()->gte($export->expires_at));
            $retry = ! $cancelled && $task->attempts < config('applicant_exports.attempts')
                && ! in_array($error, ['document_changed', 'artifact_budget_exceeded', 'pin_failed', 'excel_cell_too_long'], true);
            ExportStore::tasks()->where('id', $task->id)->update([
                'state' => $cancelled ? 'cancelled' : ($retry ? 'queued' : 'failed'),
                'owner' => null, 'lease_until' => null, 'dispatch_after' => null,
                'due_at' => now()->addSeconds(30 * 2 ** ($task->attempts - 1)), 'error' => $error,
                // An interrupted attempt can have partially written output. Keep its reservation until cleaned.
                'reservation' => $released ? 0 : $current->reservation,
            ]);
            ExportStore::settle($task->export_id);
        }, 3);
    }

    private function prune(object $task): bool
    {
        if (ExportStore::tasks()->where('export_id', $task->export_id)->where('handoff_until', '>', now())->exists()) {
            ExportStore::tasks()->where('id', $task->id)->update([
                'state' => 'queued', 'owner' => null, 'lease_until' => null, 'dispatch_after' => null, 'due_at' => now()->addMinutes(5),
            ]);
            return false;
        }
        if (File::isDirectory($this->files->directory($task->export_id)) && ! File::deleteDirectory($this->files->directory($task->export_id))) {
            throw new RuntimeException('cleanup_failed');
        }
        return ExportStore::exports()->where('id', $task->export_id)->where('expires_at', '<=', now())->delete() === 1;
    }
}
