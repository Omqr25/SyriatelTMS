<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'publisher_id',
        'department_id',
    ];

    protected $casts = ['department_id' => 'integer'];

    public function publisher()
    {
        return $this->belongsTo(User::class, 'publisher_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function audiences()
    {
        return $this->hasMany(AnnouncementAudience::class);
    }

    public function traineeAudience()
    {
        return $this->hasOne(AnnouncementAudience::class);
    }
}
