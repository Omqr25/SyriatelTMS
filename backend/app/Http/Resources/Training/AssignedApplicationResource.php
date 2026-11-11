<?php

namespace App\Http\Resources\Training;

use App\Http\Resources\User\TrainerResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignedApplicationResource extends JsonResource
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
          'name'=>$this->full_name,
          'status'=>$this->status,
            'supervisors' =>TrainerResource::make($this->assigns()->latest()->first()?->trainer),
        ];
    }
}
