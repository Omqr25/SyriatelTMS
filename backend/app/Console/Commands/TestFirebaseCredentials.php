<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

class TestFirebaseCredentials extends Command
{
    protected $signature = 'firebase:test-credentials';

    protected $description = 'Check Firebase credentials without exposing secrets';

    public function handle(): int
    {
        $path = config('firebase.projects.app.credentials');

        if (! is_string($path) || ! is_file($path)) {
            $this->error('Firebase credentials file was not found.');
            return self::FAILURE;
        }

        $credentials = json_decode(file_get_contents($path), true);

        if (
            json_last_error() !== JSON_ERROR_NONE ||
            ($credentials['type'] ?? null) !== 'service_account' ||
            empty($credentials['project_id']) ||
            empty($credentials['private_key']) ||
            empty($credentials['client_email'])
        ) {
            $this->error('Firebase credentials JSON is invalid or incomplete.');
            return self::FAILURE;
        }

        $this->info('Firebase credentials file exists and contains valid service-account JSON.');
        $this->info('Project ID: '.$credentials['project_id']);

        return self::SUCCESS;
    }
}
