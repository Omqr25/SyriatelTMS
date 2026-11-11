<?php

namespace App\Models;

use App\Enums\VacationRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VacationRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'reason',
        'start_date',
        'end_date',
        'status',
        'trainee_id',
        'trainer_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'status'=>VacationRequestStatus::class,
        ];
    }

    public function trainee()
    {
        return $this->belongsTo(Trainee::class);
    }

    public function trainer()
    {
        return $this->belongsTo(Trainer::class);
    }

    public function absences()
    {
        return $this->hasMany(Absence::class, 'request_id');
    }
}
