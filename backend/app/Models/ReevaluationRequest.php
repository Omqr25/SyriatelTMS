<?php

namespace App\Models;

use App\Enums\EvaluationRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReevaluationRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'reason',
        'status',
        'decision_note',
        'trainee_id',
        'trainer_id',
        'evaluation_id',
    ];

    protected function casts(): array
    {
        return [
            'status'=>EvaluationRequestStatus::class,
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

    public function evaluation()
    {
        return $this->belongsTo(Evaluation::class, 'evaluation_id');
    }

}
