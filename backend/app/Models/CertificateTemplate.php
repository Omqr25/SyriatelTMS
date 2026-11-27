<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CertificateTemplate extends Model
{
    protected $fillable = ['name', 'created_by', 'archived_at'];

    protected function casts(): array
    {
        return ['archived_at' => 'immutable_datetime'];
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(CertificateTemplateVersion::class)->latestOfMany('version');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CertificateTemplateVersion::class);
    }
}
