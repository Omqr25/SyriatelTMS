<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class
Trainer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'department_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function trainees()
    {
        return $this->hasMany(Trainee::class);
    }

    public function feedbacks()
    {
        return $this->hasMany(Feedback::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function interviews()
    {
        return $this->hasMany(Interview::class);
    }

    public function programs()
    {
        return $this->hasMany(Program::class);
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class);
    }

    public function reevaluationRequests()
    {
        return $this->hasMany(ReevaluationRequest::class);
    }

    public function vacationRequests()
    {
        return $this->hasMany(VacationRequest::class);
    }

    public function absences()
    {
        return $this->hasMany(Absence::class);
    }

    public function applications()
    {
        return $this->hasManyThrough(
            Application::class,
            ApplicationAssign::class,
            'trainer_id',
            'id',
            'id',
            'application_id'
        );
    }

    public function assigns()
    {
        return $this->hasMany(ApplicationAssign::class);
    }
}
