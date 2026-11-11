<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Interview extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'trainer_id',
        'time',
        'location',
        'evaluation',
        'interview_note',
    ];

    protected function casts(): array
    {
        return [
            'time' => 'datetime',
            'evaluation' => 'decimal:2',
        ];
    }

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function trainer()
    {
        return $this->belongsTo(Trainer::class);
    }
}
