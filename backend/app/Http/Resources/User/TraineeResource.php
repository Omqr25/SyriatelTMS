<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TraineeResource extends JsonResource
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
            'name' => $this->user->name,
            'email' => $this->user->email,
            'phone_number'=>$this->user->phone_number,
            'status'=>$this->user->status,
            'role'=>RoleResource::collection($this->user->roles),
            'department' => [
                'id' => $this->department?->id,
                'name' => $this->department?->name,
                'location' => $this->department?->name,  ],
        ];
    }
}
