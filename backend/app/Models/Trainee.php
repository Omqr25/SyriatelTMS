<?php

namespace App\Models;

use App\Enums\TraineeStatus;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Trainee extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'department_id',
        'trainer_id',
        'application_id',
    ];

    protected function casts(): array
    {
        return [
            'status'=>TraineeStatus::class,
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function trainer()
    {
        return $this->belongsTo(Trainer::class);
    }

    public function application()
    {
        return $this->belongsTo(Application::class);
    }


    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function evaluation()
    {
        return $this->hasOne(Evaluation::class);
    }

    public function reevaluationRequest()
    {
        return $this->hasOne(ReevaluationRequest::class);
    }

    public function vacationRequests()
    {
        return $this->hasMany(VacationRequest::class);
    }

    public function absences()
    {
        return $this->hasMany(Absence::class);
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }

    public function fire()
    {
        return $this->hasOne(FiredTrainee::class);
    }


}
