<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Absence extends Model
{
    use HasFactory;

    protected $fillable = [
        'trainee_id',
        'trainer_id',
        'reason',
        'date',
        'request_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
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

    public function vacationRequest()
    {
        return $this->belongsTo(
            VacationRequest::class,
            'request_id'
        );
    }

    public function scopeFilter($query,array $filters)
    {

        $query->when($filters['name'] ?? false, function ($query) use ($filters) {
            $query->whereHas('trainee.user', function ($query) use ($filters) {
                $query->where('name', 'like', '%' . $filters['name'] . '%');
            });
        });

        $date = $filters['date'] ?? now();

        $query->whereDate('date', $date);

        return $query;
    }
}
