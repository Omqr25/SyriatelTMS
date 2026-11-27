<?php

namespace App\Console\Commands;

use App\Services\Certificate\CertificateStorage;
use Illuminate\Console\Command;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PDOException;

final class WorkCertificates extends Command
{
    protected $signature = 'certificates:work';

    protected $description = 'Run the dedicated sequential certificate queue worker';

    public function handle(): int
    {
        $connection = DB::connection();
        $pdo = null;
        $file = null;
        $key = hash('sha256', 'certificates:work:'.$connection->getDatabaseName());
        if (in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            // Keep the advisory lock on this exact database session for the lifetime of the worker.
            $pdo = $connection->getPdo();
            $query = $pdo->prepare('SELECT GET_LOCK(?, 0)');
            $query->execute([$key]);
            $locked = (int) $query->fetchColumn() === 1;
        } else {
            $disk = Storage::disk(CertificateStorage::DISK);
            $disk->makeDirectory('certificates');
            $file = @fopen($disk->path('certificates/.worker.lock'), 'c');
            $locked = $file && flock($file, LOCK_EX | LOCK_NB);
        }

        if (! $locked) {
            if (is_resource($file)) {
                fclose($file);
            }
            $this->error('A certificate worker is already running or its lock is unavailable.');

            return self::FAILURE;
        }

        try {
            ini_set('memory_limit', '256M');
            if ($pdo !== null) {
                // A disconnected advisory-lock session must not silently resume claiming work.
                Queue::looping(function (Looping $event) use ($pdo, $key) {
                    if ($event->connectionName !== 'certificates') {
                        return null;
                    }
                    try {
                        $query = $pdo->prepare('SELECT IS_USED_LOCK(?) = CONNECTION_ID()');
                        $query->execute([$key]);
                        if ((int) $query->fetchColumn() === 1) {
                            return null;
                        }
                    } catch (PDOException) {
                        // The worker must be manually restarted after losing its lock.
                    }
                    $this->error('The certificate worker lost its database lock.');
                    app('queue.worker')->shouldQuit = true;

                    return false;
                });
            }

            return $this->call('queue:work', [
                'connection' => 'certificates', '--queue' => 'certificates',
                '--timeout' => config('certificates.job_timeout'), '--memory' => 192,
                '--sleep' => 3, '--tries' => 3,
            ]);
        } finally {
            if ($pdo !== null) {
                try {
                    $query = $pdo->prepare('SELECT RELEASE_LOCK(?)');
                    $query->execute([$key]);
                } catch (PDOException) {
                    // A closed database session has already released its lock.
                }
            }
            if (is_resource($file)) {
                fclose($file);
            }
        }
    }
}
