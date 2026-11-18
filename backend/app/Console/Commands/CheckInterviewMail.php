<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckInterviewMail extends Command
{
    protected $signature = 'interviews:mail-check';

    protected $description = 'Check interview mail configuration without exposing credentials or sending mail';

    public function handle(): int
    {
        $mailer = config('mail.default');
        $connection = config('queue.default');
        $driver = config("queue.connections.{$connection}.driver");
        $this->info("Mailer: {$mailer}; queue: {$connection} ({$driver})");
        $valid = true;
        if (in_array($mailer, ['log', 'array'], true)) {
            $this->error('This mailer does not deliver email to Gmail or any inbox. Configure SMTP or a mail provider.');
            $valid = false;
        }
        if ($driver === 'null') {
            $this->error('The null queue discards notification jobs.');
            $valid = false;
        } elseif (! in_array($driver, ['sync', 'deferred', 'background'], true)) {
            $this->warn('Run a persistent queue worker using this same backend configuration and database.');
        }
        $sender = config('mail.from.address');
        if (! filter_var($sender, FILTER_VALIDATE_EMAIL) || str_ends_with($sender, '@example.com')) {
            $this->error('Set MAIL_FROM_ADDRESS to a valid sender accepted by your mail provider.');
            $valid = false;
        }
        if ($mailer === 'smtp' && in_array(config('mail.mailers.smtp.host'), ['127.0.0.1', 'localhost', null, ''], true)) {
            $this->warn('SMTP points to localhost. Ensure a real SMTP server is running there, or configure your provider host.');
        }
        if ($mailer === 'resend' && ! config('services.resend.key')) {
            $this->error('The Resend API key is missing. Set it in the backend environment.');
            $valid = false;
        }
        if ($mailer === 'failover') {
            $this->warn('Check every failover transport: a log fallback records messages without delivering them.');
        }
        if ($valid) {
            $this->info('Configuration checks passed. Provider authentication, worker health and inbox delivery still require verification.');
        }

        return $valid ? self::SUCCESS : self::FAILURE;
    }
}
