<?php

namespace App\Models;

use App\Enums\VacationRequestStatus;
use Illuminate\Database\Eloquent\Builder;
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

    public function scopeCoveringAbsence(Builder $query): Builder
    {
        return $query->whereColumn('vacation_requests.trainee_id', 'absences.trainee_id')
            ->where('vacation_requests.status', VacationRequestStatus::ACCEPTED)
            ->whereRaw('DATE(vacation_requests.start_date) <= DATE(absences.date)')
            ->whereRaw('DATE(vacation_requests.end_date) >= DATE(absences.date)');
    }

    public function scopeOverlapping(Builder $query, string $start, string $end): Builder
    {
        return $query->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start);
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
