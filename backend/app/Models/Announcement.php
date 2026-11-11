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
    ];

    public function publisher()
    {
        return $this->belongsTo(User::class, 'publisher_id');
    }

    public function audiences()
    {
        return $this->hasMany(AnnouncementAudience::class);
    }

    public function traineeAudience()
    {
        return $this->hasOne(AnnouncementAudience::class, 'announcement_id')
            ->where('audience_id', auth('api')->user()?->id);
    }
}
