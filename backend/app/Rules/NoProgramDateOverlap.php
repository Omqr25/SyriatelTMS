<?php

namespace App\Rules;

use App\Models\TrainingProgram;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class NoProgramDateOverlap implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function __construct(private string $startField, private string $endField) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void {

        $start = request($this->startField);
        $end = request($this->endField);

        $exists = TrainingProgram::where(function ($query) use ($start, $end) {
            $query->whereBetween($this->startField, [$start, $end])
                ->orWhereBetween($this->endField, [$start, $end])
                ->orWhere(function ($q) use ($start, $end) {
                    $q->where($this->startField, '<=', $start)
                        ->where($this->endField, '>=', $end);});
        })->exists();

        if ($exists) {
            $fail('This period overlaps with another training program.');
        }
    }
}
