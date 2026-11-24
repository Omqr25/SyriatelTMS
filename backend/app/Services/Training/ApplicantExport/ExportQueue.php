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
    private const EXCEL_BURST = 3;

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
        ExportStore::tasks()->where('kind', 'prune')->whereNull('owner')->where(fn ($query) => $query->where('state', 'cancelled')
            ->orWhere(fn ($query) => $query->where('state', 'failed')->where('due_at', '<=', now()->subHour())))
            ->update(['state' => 'queued', 'attempts' => 0, 'lock_failures' => 0, 'dispatch_after' => null, 'due_at' => now()]);
        $this->dispatch();
    }

    public function dispatch(?string $id = null): void
    {
        // Reconciliation is bounded and only enqueues. No ZIP or document I/O runs in the scheduler.
        $jobTable = config('queue.connections.applicant_exports.table');
        $tasks = ExportStore::tasks()->where('state', 'queued')->where('due_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('dispatch_after')->orWhere('dispatch_after', '<=', now()))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from($jobTable)
                ->whereColumn($jobTable.'.id', 'applicant_export_tasks.queue_job_id'))
            ->when($id, fn ($query) => $query->where('export_id', $id))
            ->orderByRaw("CASE kind WHEN 'prune' THEN 0 WHEN 'excel' THEN 1 ELSE 2 END")->orderBy('id')
            ->limit(8)->get(['id', 'kind']);
        foreach ($tasks as $task) {
            $taskId = $task->id;
            DB::transaction(function () use ($task, $taskId) {
                $changed = ExportStore::tasks()->where('id', $taskId)->where('state', 'queued')
                    ->where(fn ($query) => $query->whereNull('dispatch_after')->orWhere('dispatch_after', '<=', now()))
                    ->update(['dispatch_after' => now()->addMinutes(15)]);
                if ($changed) {
                    $jobId = Bus::dispatch(new ProcessApplicantExport($taskId, $task->kind));
                    if (is_numeric($jobId)) ExportStore::tasks()->where('id', $taskId)->update(['queue_job_id' => $jobId]);
                }
            }, 3);
        }
    }

    public function process(int $taskId): void
    {
        $candidate = ExportStore::tasks()->find($taskId);
        if (! $candidate || $candidate->state !== 'queued' || now()->lt($candidate->due_at)) return;

        $taskLock = $this->files->lock($candidate->export_id.'-'.$taskId, true, $busy);
        if (! $taskLock) {
            $this->lockFailed($candidate, $busy);

            return;
        }
        $exportLock = null;
        $temporary = null;
        $task = null;
        $publishing = false;
        $pruned = false;
        try {
            $exportLock = $this->files->lock($candidate->export_id, $candidate->kind === 'prune', $busy);
            if (! $exportLock) {
                $this->lockFailed($candidate, $busy);

                return;
            }
            $directory = $this->files->directory($candidate->export_id);
            if ($candidate->kind !== 'prune') {
                // Both filesystem locks are held. A queued task may still have an unpublished attempt.
                foreach (File::glob("$directory/attempt-$taskId-*") as $orphan) {
                    if (basename($orphan) !== $candidate->file && ! File::delete($orphan)) {
                        $this->lockFailed($candidate, false, 'temporary_cleanup_failed');
                        return;
                    }
                }
            }
            clearstatcache();
            $free = disk_free_space($this->files->directory('.locks'));
            $task = $this->claim($candidate, $free === false ? 0 : $free);
            if (! $task) return;

            if ($task->kind === 'prune') {
                $pruned = $this->prune($task);
                return;
            }

            File::ensureDirectoryExists($directory, 0700);
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
            if (in_array($task->kind, ['excel', 'zip'], true) && ! is_file($temporary)) {
                throw new RuntimeException('artifact_missing');
            }
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
            $this->dispatch();
        }
    }

    private function lockFailed(object $candidate, bool $busy, string $reason = 'storage_unavailable'): void
    {
        if ($busy) {
            // A live download or task owns the lock. It is safe to wait for its release.
            $this->defer($candidate->id);
            return;
        }

        DB::transaction(function () use ($candidate, $reason) {
            $export = ExportStore::exports()->where('id', $candidate->export_id)->lockForUpdate()->first();
            $task = ExportStore::tasks()->where('id', $candidate->id)->where('state', 'queued')->lockForUpdate()->first();
            if (! $export || ! $task) return;
            $failures = $task->lock_failures + 1;
            $terminal = $failures >= config('applicant_exports.attempts');
            ExportStore::tasks()->where('id', $task->id)->update([
                'state' => $terminal ? 'failed' : 'queued', 'error' => $reason, 'lock_failures' => $failures,
                'dispatch_after' => null, 'due_at' => now()->addSeconds(15 * $failures),
            ]);
            if ($terminal) ExportStore::settle($task->export_id, $task->kind);
        }, 3);
    }

    private function defer(int $id): void
    {
        ExportStore::tasks()->where('id', $id)->where('state', 'queued')
            ->update(['dispatch_after' => null, 'due_at' => now()->addSeconds(15)]);
    }

    private function claim(object $candidate, float $free): ?object
    {
        return DB::transaction(function () use ($candidate, $free) {
            $capacity = DB::table('applicant_export_capacity')->where('id', 1);
            $streak = $capacity->lockForUpdate()->first()->excel_streak;
            $export = ExportStore::exports()->where('id', $candidate->export_id)->lockForUpdate()->first();
            $task = ExportStore::tasks()->where('id', $candidate->id)->lockForUpdate()->first();
            if (! $export || ! $task || $task->state !== 'queued' || now()->lt($task->due_at)) return null;

            if ($task->kind === 'prune' && now()->lt($export->expires_at)) {
                ExportStore::tasks()->where('id', $task->id)->update(['due_at' => $export->expires_at, 'dispatch_after' => null]);
                return null;
            }
            if ($task->kind !== 'prune' && now()->gte($export->expires_at)) {
                ExportStore::tasks()->where('id', $task->id)->update(['state' => 'cancelled']);
                ExportStore::settle($export->id, $task->kind);
                return null;
            }
            if (ExportStore::tasks()->whereNotNull('owner')->exists()) {
                $this->defer($task->id);
                return null;
            }

            $excel = $task->kind === 'excel';
            $due = ExportStore::tasks()->join('applicant_exports', 'applicant_exports.id', '=', 'applicant_export_tasks.export_id')
                ->where('state', 'queued')->where('due_at', '<=', now())
                ->where(fn ($query) => $query->where('kind', 'prune')->orWhere('expires_at', '>', now()));
            $ordinaryWaiting = $excel && (clone $due)->where('kind', '!=', 'excel')->exists();
            if (($ordinaryWaiting && $streak >= self::EXCEL_BURST)
                || (! $excel && $streak < self::EXCEL_BURST && (clone $due)->where('kind', 'excel')->exists())) {
                $this->defer($task->id);
                return null;
            }

            if ($task->estimate && ! $this->reserve($task, $free)) {
                ExportStore::settle($export->id, $task->kind);
                return null;
            }

            $capacity->update(['excel_streak' => $ordinaryWaiting ? min(self::EXCEL_BURST, $streak + 1) : 0]);
            $required = $task->estimate;
            $owner = (string) Str::uuid();
            $attempts = $task->attempts + 1;
            ExportStore::tasks()->where('id', $task->id)->update([
                'state' => 'running', 'owner' => $owner, 'attempts' => $attempts,
                'lease_until' => now()->addSeconds(config('queue.connections.applicant_exports.retry_after')),
                'reservation' => $required, 'progress' => 0, 'error' => null, 'lock_failures' => 0,
            ]);

            return (object) array_replace((array) $task, ['owner' => $owner, 'attempts' => $attempts, 'reservation' => $required]);
        }, 3);
    }

    /** Admit all unfinished ZIP parts together while the singleton capacity row is locked. */
    private function reserve(object $task, float $free): bool
    {
        $excel = $task->kind === 'excel';
        $allowance = max(0, min(config('applicant_exports.excel_storage_bytes'), config('applicant_exports.storage_bytes')));
        $pending = ExportStore::tasks()->where('export_id', $task->export_id)->where('kind', $task->kind)->where('state', 'queued');
        $remaining = (clone $pending)->sum('estimate');
        $committed = ExportStore::tasks()->where('kind', $task->kind)->sum('reservation') - (clone $pending)->sum('reservation');
        // Space for Excel is withheld from both document quota and the document free-space floor.
        $quota = ($excel ? $allowance : config('applicant_exports.storage_bytes') - $allowance) - $committed;
        $otherPending = ExportStore::tasks()->where('kind', $task->kind)->where('export_id', '!=', $task->export_id)
            ->where('state', 'queued')->sum('reservation');
        $available = (int) max(0, min($quota, $free - config('applicant_exports.min_free_bytes')
            - ($excel ? 0 : $allowance) - $otherPending));
        if ($remaining > $available) {
            (clone $pending)->where('attempts', 0)->update(['reservation' => 0]);
            $pending->update(['state' => 'failed', 'error' => 'storage_exhausted',
                'capacity_required' => $remaining, 'capacity_available' => $available]);

            return false;
        }
        $pending->update(['reservation' => DB::raw('estimate'), 'capacity_required' => null, 'capacity_available' => null]);

        return true;
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
            ExportStore::settle($task->export_id, $task->kind);
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
                // The transport row from a killed worker can still be reserved until Laravel reaps it.
                'queue_job_id' => $error === 'worker_interrupted' ? null : $current->queue_job_id,
                'due_at' => now()->addSeconds(30 * 2 ** ($task->attempts - 1)), 'error' => $error,
                // An interrupted attempt can have partially written output. Keep its reservation until cleaned.
                'reservation' => $released ? 0 : $current->reservation,
            ]);
            ExportStore::settle($task->export_id, $task->kind);
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
