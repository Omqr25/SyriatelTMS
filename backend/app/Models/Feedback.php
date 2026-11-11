<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Feedback extends Model
{
    /** @use HasFactory<\Database\Factories\FeedbackFactory> */
    use HasFactory;
    protected $table = 'feedbacks';

    protected $guarded = ['id'];

    protected $casts=[
      'status'=>TaskStatus::class,
    ];

    public function trainer()
    {
        return $this->belongsTo(Trainer::class, 'trainer_id');
    }

    public function sourceEvidence()
    {
        return $this->belongsTo(Evidence::class, 'evidence_id');
    }

    public function targetEvidence()
    {
        return $this->belongsTo(Evidence::class, 'feedback_id');
    }
}
