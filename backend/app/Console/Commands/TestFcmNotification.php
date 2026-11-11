<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;


class TestFcmNotification extends Command
{
    /**
     * Execute the console command.
     */
    protected $signature = 'fcm:test {userId=1}';

    protected $description = 'Send a test Firebase push notification';

    public function handle(Messaging $messaging): int
    {
        $user = User::find($this->argument('userId'));

        if (!$user) {
            $this->error('User not found.');
            return self::FAILURE;
        }

        $token = $user->deviceTokens()->value('token');

        if (!$token) {
            $this->error('No FCM token registered for this user.');
            return self::FAILURE;
        }

        try {
            $message = CloudMessage::new()
                ->withToken($token)
                ->withNotification(
                    FcmNotification::create(
                        'Firebase Test',
                        'Your Laravel push notification is working!'
                    )
                );

            $messaging->send($message);

            $this->info('Firebase accepted the notification.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
