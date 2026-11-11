<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Enums\ApplicationStatus;

class ApplicationAssign extends Model
{
    /** @use HasFactory<\Database\Factories\ApplicationAssignFactory> */
    use HasFactory;

    protected $guarded=['id'];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
        ];
    }
    public function trainer()
    {
        return $this->belongsTo(Trainer::class);
    }

    public function application()
    {
        return $this->belongsTo(Application::class);
    }
}
