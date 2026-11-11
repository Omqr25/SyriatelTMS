<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'location',
    ];

    public function trainers()
    {
        return $this->hasMany(Trainer::class);
    }

    public function trainees()
    {
        return $this->hasMany(Trainee::class);
    }

    public function programs()
    {
        return $this->hasMany(Program::class);
    }
}
