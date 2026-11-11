<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnnouncementAudience extends Model
{
    use HasFactory;

    protected $fillable = [
        'announcement_id',
        'audience_id',
        'read',
    ];

    protected function casts(): array
    {
        return [
            'read' => 'boolean',
        ];
    }

    public function announcement()
    {
        return $this->belongsTo(Announcement::class);
    }

    public function audience()
    {
        return $this->belongsTo(User::class, 'audience_id');
    }
}
