<?php

namespace App\Models;

use App\Enums\TrainingProgramStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingProgram extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'application_start_date',
        'application_end_date',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'application_start_date' => 'date',
            'application_end_date' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => TrainingProgramStatus::class,
        ];
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }
}
