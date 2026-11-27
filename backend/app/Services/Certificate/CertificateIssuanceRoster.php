<?php

namespace App\Services\Certificate;

use App\Models\Certificate;
use App\Models\CertificateBatch;
use App\Models\CertificateTemplateVersion;
use App\Models\Trainee;
use App\Models\TrainingProgram;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CertificateIssuanceRoster
{
    public const STATUSES = ['ready', 'excluded', 'fired', 'program_not_ended', 'queued', 'generating', 'issued', 'failed'];

    public function __construct(
        private CertificateRoster $roster,
        private CertificateNames $names,
        private CertificatePreparation $preparation,
    ) {}

    public function page(TrainingProgram $program, array $filters): array
    {
        $base = $this->query($program);
        $counts = (clone $base)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $rows = (clone $base)
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
                $query->where(fn (Builder $query) => $query
                    ->whereRaw("name LIKE ? ESCAPE '!'", [$like])->orWhereRaw("email LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("application_name LIKE ? ESCAPE '!'", [$like]));
            })
            ->orderBy('id')->paginate(12);
        $trainees = Trainee::with(['user', 'application'])->whereIn('id', $rows->pluck('id'))->get()->keyBy('id');
        $frozen = Certificate::whereIn('id', $rows->pluck('certificate_id')->filter())->get()->keyBy('id');
        $rows->through(function ($row) use ($program, $trainees, $frozen) {
            $certificate = $frozen->get($row->certificate_id);
            $row->certificate_name = $certificate
                ? ($certificate->snapshot['trainee_name'] ?? $row->name)
                : $this->names->resolve($program, $trainees->get($row->id))['name'] ?? null;

            return $row;
        });

        return [
            'roster' => $rows, 'counts' => $counts,
            'ready_ids' => (clone $base)->where('status', 'ready')->pluck('id'),
            'name_window' => $this->names->window($program),
            'batches' => CertificateBatch::where('training_program_id', $program->id)->select('id', 'confirmed_at')
                ->withCount(['certificates as total', 'certificates as issued' => fn ($query) => $query->where('state', 'issued'),
                    'certificates as failed' => fn ($query) => $query->where('state', 'failed')])
                ->latest('id')->limit(5)->get(),
        ];
    }

    public function plan(TrainingProgram $program, CertificateTemplateVersion $version, array $selection): array
    {
        if ($version->template->archived_at !== null) {
            throw ValidationException::withMessages(['issuance' => __('certificates.template_archived')]);
        }
        $now = $this->roster->time();
        if ($now->lt($this->roster->cutoff($program))) {
            throw ValidationException::withMessages(['issuance' => __('certificates.program_not_ended')]);
        }
        $override = isset($selection['override_reason']);
        $query = $this->query($program)->orderBy('id');
        $requested = array_unique($selection['trainee_ids'] ?? []);
        if ($selection['mode'] === 'explicit') {
            if ($override && count($requested) !== 1) {
                throw ValidationException::withMessages(['issuance' => __('certificates.override_individual')]);
            }
            if ($override) {
                CertificateText::reason($selection['override_reason']);
            }
            $query->whereIn('id', $requested);
        } else {
            $query->where('status', 'ready')->whereNotIn('id', $selection['except_ids'] ?? []);
        }

        $ids = [];
        $blocked = [];
        $seen = [];
        $query->chunkById(100, function ($rows) use ($program, $version, $override, $now, &$ids, &$blocked, &$seen) {
            $trainees = Trainee::with(['application', 'user', 'department'])->whereIn('id', $rows->pluck('id'))->get()->keyBy('id');
            foreach ($rows as $row) {
                $seen[] = $row->id;
                try {
                    if ($override ? ! in_array($row->status, ['excluded', 'fired'], true) : $row->status !== 'ready') {
                        throw ValidationException::withMessages(['issuance' => __('certificates.not_eligible', ['trainee' => $row->id])]);
                    }
                    // Confirmation rechecks fit using the exact allocated number and issue date.
                    $this->preparation->snapshot($program, $trainees->get($row->id), $version, $now->toDateString(), 'CERT-'.$now->year.'-888888');
                    $ids[] = $row->id;
                } catch (ValidationException $failure) {
                    $blocked[] = ['id' => $row->id, 'name' => $row->name, 'reason' => $failure->validator->errors()->first()];
                }
            }
        });
        foreach (array_diff($requested, $seen) as $missing) {
            $blocked[] = ['id' => $missing, 'name' => null, 'reason' => __('certificates.wrong_program')];
        }

        return ['trainee_ids' => $ids, 'blocked' => $blocked];
    }

    private function query(TrainingProgram $program): Builder
    {
        $subquery = DB::table('trainees as t')->join('applications as a', 'a.id', '=', 't.application_id')
            ->join('users as u', 'u.id', '=', 't.user_id')->leftJoin('certificates as c', function ($join) {
                $join->on('c.trainee_id', '=', 't.id')->on('c.training_program_id', '=', 'a.training_program_id');
            })
            ->where('a.training_program_id', $program->id)
            ->select('t.id', 'u.name', 'u.email', 'a.full_name as application_name', 'c.id as certificate_id', 'c.failure_code', 'c.number')
            ->selectRaw("CASE WHEN c.id IS NOT NULL THEN c.state
                WHEN ? = 0 THEN 'program_not_ended'
                WHEN (SELECT action FROM certificate_events WHERE trainee_id = t.id AND training_program_id = a.training_program_id
                    AND action IN ('excluded', 'restored') ORDER BY id DESC LIMIT 1) = 'excluded' THEN 'excluded'
                WHEN t.status = 'fired' THEN 'fired' ELSE 'ready' END as status",
                [(int) $this->roster->time()->gte($this->roster->cutoff($program))]);

        return DB::query()->fromSub($subquery, 'roster_rows');
    }
}
