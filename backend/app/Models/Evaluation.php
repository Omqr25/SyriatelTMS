<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'score',
        'description',
        'trainee_id',
        'trainer_id',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
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

    public function criteria()
    {
        return $this->hasMany(Criteria::class);
    }

    public function reevaluationRequests()
    {
        return $this->hasMany(ReevaluationRequest::class, 'evaluation_id');
    }
}
