<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\City;
use App\Enums\HowHeard;
use App\Enums\YearOfStudy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use PHPUnit\Logging\OpenTestReporting\Status;

class Application extends Model
{
    use HasFactory;

    protected $guarded=['id'];

    protected $casts=[
        'city'=>City::class,
        'year_of_study'=>YearOfStudy::class,
        'how_heard'=>HowHeard::class,
        'status'=>ApplicationStatus::class,
        'birth_date'=>'date',
    ];


    public function interviews()
    {
        return $this->hasMany(Interview::class);
    }

    public function trainee()
    {
        return $this->hasOne(Trainee::class);
    }

    public function Trainer()
    {
        return $this->belongsToMany(Trainer::class, 'application_assigns');
    }

    public function assigns()
    {
        return $this->hasMany(ApplicationAssign::class);
    }

    public function trainingProgram()
    {
        return $this->belongsTo(TrainingProgram::class);
    }

    public function scopeFilter($query,array $filters)
    {

        $query->when($filters['search'] ?? false ,function ($query) use ($filters){
            $search=$filters['search'];
            $query->where(function ($query) use ($search){
                foreach (['full_name', 'email','phone_number'] as $column) {
                    $query->orWhere($column, 'like', "%{$search}%");
                }

            });
        });

        $query->when($filters['status'] ?? false , function ($query) use ($filters){
            $query->where('status',$filters['status']);
        });
        $query->when($filters['city'] ?? false , function ($query) use ($filters){
            $query->where('city',$filters['city']);
        });
        $query->when($filters['gpa'] ?? false, function ($query) use ($filters) {
            $query->where('gpa', '>=', $filters['gpa']);
        });
        $query->when($filters['field_of_study'] ?? false , function ($query) use ($filters){
            $query->where('field_of_study',$filters['field_of_study']);
        });
        $query->when($filters['year_of_study'] ?? false , function ($query) use ($filters){
            $query->where('year_of_study',$filters['year_of_study']);
        });
        $query->when($filters['submission_date'] ?? false , function ($query) use ($filters){
            $query->where('created_at',$filters['created_at']);
        });
        return $query;
    }
}
