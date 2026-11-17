<?php

namespace App\Notifications;

use NotificationChannels\Fcm\FcmChannel;

class TaskEvidenceSubmittedNotification extends TmsPushNotification
{
    public function via(object $notifiable): array
    {
        // TaskService writes the durable in-app notification and recipient in
        // the submission transaction. The app uses a custom notifications schema,
        // not Laravel's database notification schema; only push is queued here.
        return [FcmChannel::class];
    }
}
