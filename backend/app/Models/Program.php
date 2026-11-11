<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'department_id',
        'trainer_id',
        'training_program_id',
        'deadline',
        'status',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function trainer()
    {
        return $this->belongsTo(Trainer::class);
    }

    public function milestones()
    {
        return $this->hasMany(Milestone::class);
    }
}
