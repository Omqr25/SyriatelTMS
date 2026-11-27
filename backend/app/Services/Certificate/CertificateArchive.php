<?php

namespace App\Services\Certificate;

use App\Models\Certificate;
use App\Models\TrainingProgram;
use Illuminate\Database\Eloquent\Builder;

final class CertificateArchive
{
    public function programs(): array
    {
        return TrainingProgram::query()->select('id', 'name', 'start_date', 'end_date')
            ->whereHas('certificates', fn (Builder $query) => $query->issued())
            ->withCount(['certificates as issued_count' => fn (Builder $query) => $query->issued()])
            ->orderByDesc('end_date')->orderByDesc('id')->get()->map(fn (TrainingProgram $program) => [
                'id' => $program->id, 'name' => $program->name, 'start_date' => $program->start_date->toDateString(),
                'end_date' => $program->end_date->toDateString(), 'issued_count' => $program->issued_count,
            ])->all();
    }

    public function certificates(TrainingProgram $program, ?string $search): array
    {
        $issued = $program->certificates()->issued();
        $total = (clone $issued)->count();
        $search = trim($search ?? '');
        if ($search !== '') {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search)).'%';
            $issued->where(function (Builder $query) use ($like, $search) {
                if (ctype_digit($search) && (string) (int) $search === ltrim($search, '0')) {
                    $query->where('trainee_id', (int) $search);

                    return;
                }

                // Laravel compiles the JSON selector for the current database driver.
                $snapshotName = $query->getQuery()->getGrammar()->wrap('snapshot->trainee_name');
                $query->whereRaw("LOWER(number) LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("LOWER($snapshotName) LIKE ? ESCAPE '!'", [$like])
                    ->orWhereHas('trainee.user', fn (Builder $user) => $user
                        ->where(fn (Builder $user) => $user->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$like])
                            ->orWhereRaw("LOWER(email) LIKE ? ESCAPE '!'", [$like])));
            });
        }

        return [
            'total' => $total,
            'results' => $issued->with('trainee.user')->orderByDesc('issue_date')->orderByDesc('id')->paginate(12)
                ->through(fn (Certificate $certificate) => [
                    'id' => $certificate->id, 'trainee_id' => $certificate->trainee_id,
                    'trainee_name' => $certificate->snapshot['trainee_name'] ?? $certificate->trainee->user->name,
                    'email' => $certificate->trainee->user->email, 'number' => $certificate->number,
                    'program_name' => $program->name, 'issue_date' => $certificate->issue_date->toDateString(),
                ]),
        ];
    }
}
