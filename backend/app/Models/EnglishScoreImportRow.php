<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnglishScoreImportRow extends Model
{
    public $timestamps = false;

    protected $casts = ['score' => 'decimal:2', 'previous_score' => 'decimal:2'];
}
