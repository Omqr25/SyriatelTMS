<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

class TrainingUpdateNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $title,
        public string $message,
        public array $data = [],
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {

        return [FcmChannel::class];

//        return [
//            'database',
//            FcmChannel::class,
//        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('The introduction to the notification.')
            ->action('Notification Action', url('/'))
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'data' => $this->data,
        ];
    }



    public function toFcm(object $notifiable): FcmMessage
    {
        $data = [];

        foreach ($this->data as $key => $value) {
            if (is_scalar($value)) {
                $data[(string) $key] = (string) $value;
            }
        }

        return (new FcmMessage(
            notification: new FcmNotification(
                title: $this->title,
                body: $this->message,
            ),
        ))->data($data);
    }
    public function failed(\Throwable $exception): void
    {
        \Illuminate\Support\Facades\Log::error(
            'TrainingUpdateNotification failed',
            [
                'message' => $exception->getMessage(),
            ]
        );
    }

}
