<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    /** @use HasFactory<\Database\Factories\RoleFactory> */
    use HasFactory;
    protected $fillable=[
        'role',
        ];

    protected function casts(): array
    {
        return [
            'role'=> UserRole::class
        ];
    }
    public function users()
    {
        return $this->belongsToMany(User::class,'user_roles')->withPivot('assigned_at');
    }

}
