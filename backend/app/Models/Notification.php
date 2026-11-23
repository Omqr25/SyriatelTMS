<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    public const ANNOUNCEMENT_SYNC = 'announcement_sync';

    protected $fillable = [
        'title',
        'description',
        'notifier_id',
        'event_key',
        'type',
        'metadata',
    ];

    protected $casts = ['metadata' => 'array'];

    public function scopeVisibleInInbox(Builder $query): Builder
    {
        return $query->where(fn (Builder $query) => $query->whereNull('notifications.type')
            ->orWhere('notifications.type', '<>', self::ANNOUNCEMENT_SYNC));
    }

    public function scopeForAnnouncements(Builder $query, array $announcementIds): Builder
    {
        $originalEvents = array_map(fn ($id) => "announcement:$id", $announcementIds);

        return $query->where('type', 'announcement')->where(fn (Builder $query) => $query
            ->whereIn('event_key', $originalEvents)
            ->orWhereIn('metadata->announcement_id', $announcementIds));
    }

    public function notifier()
    {
        return $this->belongsTo(User::class, 'notifier_id');
    }

    public function notified()
    {
        return $this->hasMany(Notified::class);
    }


}
