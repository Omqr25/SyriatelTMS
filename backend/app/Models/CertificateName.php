<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateName extends Model
{
    public const TRAINEE = 'trainee';

    public const HR = 'hr';

    protected $fillable = ['trainee_id', 'training_program_id', 'source', 'name', 'updated_by'];
}
