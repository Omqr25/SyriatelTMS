<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
//        dd($this->trainee?->department?->name);
//        dd($this->trainer->department->name);

           return [
               'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone_number'=>$this->phone_number,
            'status'=>$this->status,
            'role'=>RoleResource::collection($this->roles),
               'department' => [
                   'id' => $this->trainer?->department?->id,
                   'name' => $this->trainer?->department?->name,
                   'location' => $this->trainer?->department?->name,
               ],

           ];


    }
}
