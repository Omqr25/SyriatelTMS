<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Absence extends Model
{
    use HasFactory, SoftDeletes;

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

    public function scopeWithClassification(Builder $query): Builder
    {
        return $query->addSelect('absences.*')->addSelect([
            'is_excused' => VacationRequest::coveringAbsence()->selectRaw('1')->limit(1),
        ]);
    }

    public function scopeClassification(Builder $query, bool $excused): Builder
    {
        $coverage = VacationRequest::coveringAbsence()->selectRaw('1')->toBase();

        return $excused ? $query->whereExists($coverage) : $query->whereNotExists($coverage);
    }
}
