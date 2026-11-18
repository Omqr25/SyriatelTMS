<?php

namespace App\Jobs;

use App\Services\Notification\InboxPushDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeliverInboxPush implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public function __construct(public int $deliveryId) {}

    public function handle(InboxPushDelivery $delivery): void
    {
        $delivery->deliver($this->deliveryId);
    }
}
