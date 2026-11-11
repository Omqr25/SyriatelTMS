<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'notifier_id',
    ];

    public function notifier()
    {
        return $this->belongsTo(User::class, 'notifier_id');
    }

    public function notified()
    {
        return $this->hasMany(Notified::class);
    }


}
