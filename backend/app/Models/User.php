<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'phone_number',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status'=>UserStatus::class,
        ];
    }


    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    public function trainer()
    {
        return $this->hasOne(Trainer::class);
    }

    public function trainee()
    {
        return $this->hasOne(Trainee::class , 'user_id' , 'id');
    }

    public function publishedAnnouncements()
    {
        return $this->hasMany(Announcement::class, 'publisher_id');
    }

    public function announcementAudiences()
    {
        return $this->hasMany(AnnouncementAudience::class, 'audience_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'notifier_id');
    }

    public function notified()
    {
        return $this->hasMany(Notified::class, 'notified_id');
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(\App\Models\UserRole::class, 'user_id');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class,'user_roles')->withPivot('assigned_at');
    }



    public function traineeFiresMade(): HasMany
    {
        return $this->hasMany(FiredTrainee::class, 'fired_bY');
    }

    public function deviceTokens()
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function routeNotificationForFcm(): array
    {
        return $this->deviceTokens()
            ->pluck('token')
            ->all();
    }

}
