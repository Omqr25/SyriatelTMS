<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FiredTrainee extends Model
{
    /** @use HasFactory<\Database\Factories\FiredTraineeFactory> */
    use HasFactory;

    protected $guarded=['id'];
    protected function casts(): array
    {
        return [
            'fired_at' => 'datetime',
        ];
    }
    public function trainee()
    {
        return $this->belongsTo(Trainee::class);
    }

    public function firedBy()
    {
        return $this->belongsTo(User::class, 'fired_by');
    }
}
