<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use PHPUnit\Logging\OpenTestReporting\Status;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'deadline',
        'milestone_id',
        'status',
        'instructions',
        'priority',
        'started_at',
        'completed_at',
        'trainee_id',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'datetime',
            'status' => TaskStatus::class,
        ];
    }

    public function milestone()
    {
        return $this->belongsTo(Milestone::class);
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }

    public function trainee()
    {
        return $this->belongsTo(Trainee::class);
    }


    public function evidences()
    {
        return $this->hasMany(Evidence::class);
    }



}
