<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\City;
use App\Enums\HowHeard;
use App\Enums\YearOfStudy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PHPUnit\Logging\OpenTestReporting\Status;

class Application extends Model
{
    use HasFactory;

    public const MIN_ADMISSION_GPA = 70;

    public const MIN_ADMISSION_ENGLISH_SCORE = 45;

    protected $guarded=['id'];

    protected $casts=[
        'english_test_score' => 'decimal:2',
        'city'=>City::class,
        'year_of_study'=>YearOfStudy::class,
        'how_heard'=>HowHeard::class,
        'status'=>ApplicationStatus::class,
        'birth_date'=>'date',
    ];


    public function latestAssignment()
    {
        return $this->hasOne(ApplicationAssign::class)->latestOfMany();
    }

    public function latestInterview()
    {
        return $this->hasOne(Interview::class)->latestOfMany();
    }

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

    public function applicationForm(): BelongsTo
    {
        return $this->belongsTo(ApplicationForm::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ApplicationAnswer::class);
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
        $query->when(isset($filters['gpa_min']) && $filters['gpa_min'] !== '', function ($query) use ($filters) {
            $query->where('gpa', '>=', $filters['gpa_min']);
        });
        $query->when(isset($filters['gpa_max']) && $filters['gpa_max'] !== '', function ($query) use ($filters) {
            $query->where('gpa', '<=', $filters['gpa_max']);
        });
        foreach ([
            'gpa_eligibility' => ['gpa', self::MIN_ADMISSION_GPA],
            'english_eligibility' => ['english_test_score', self::MIN_ADMISSION_ENGLISH_SCORE],
        ] as $filter => [$column, $minimum]) {
            match ($filters[$filter] ?? null) {
                'eligible' => $query->where($column, '>=', $minimum),
                'not_eligible' => $query->where($column, '<', $minimum),
                'pending' => $column === 'english_test_score' ? $query->whereNull($column) : null,
                default => null,
            };
        }
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
