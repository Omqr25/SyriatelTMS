<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateNameWindow extends Model
{
    protected $primaryKey = 'training_program_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['training_program_id', 'opened_at', 'opened_by'];

    protected function casts(): array
    {
        return ['opened_at' => 'immutable_datetime'];
    }
}
