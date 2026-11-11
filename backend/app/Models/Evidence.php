<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evidence extends Model
{
    /** @use HasFactory<\Database\Factories\EvidenceFactory> */
    use HasFactory;

    protected $table = 'evidences';

    protected $guarded=['id'];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function sourceFeedback()
    {
        return $this->belongsTo(Feedback::class ,'feedback_id');
    }

    public function targetFeedback()
    {
        return $this->hasOne(Feedback::class , 'evidence_id' );
    }
}
