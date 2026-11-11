<?php

namespace App\Http\Resources\Announcemnt;

use App\Http\Resources\User\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use function CuyZ\Valinor\Compiler\this;

class AnnouncemntResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
          'id'=>$this->id,
          'title'=>$this->title,
          'description'=>$this->description,
          'publisher'=>UserResource::make($this->publisher),
            'announced_at'=>$this->created_at->format('Y-m-d H:i'),
          'audiences_count'  => $this->audiences_count,
            'read'=>$this->traineeAudience?->read,
//            'audiences'=>UserResource::collection($this->whenLoaded('audiences.audience'))

        ];
    }
}
