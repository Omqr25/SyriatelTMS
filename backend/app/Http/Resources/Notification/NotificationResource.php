<?php

namespace App\Http\Resources\Notification;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'metadata' => $this->metadata,
            'read' => $this->whenHas('recipient_read_at', fn () => $this->recipient_read_at !== null),
            'read_at' => $this->whenHas('recipient_read_at', fn ($date) => $date?->toIso8601String()),
            'notifier_id' => $this->notifier_id,
            'notifier_name' => $this->whenLoaded(
                'notifier',
                fn () => $this->notifier?->name
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
