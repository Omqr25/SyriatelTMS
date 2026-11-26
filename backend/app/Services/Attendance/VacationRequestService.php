<?php

namespace App\Services\Attendance;

use App\Enums\VacationRequestStatus;
use App\Models\Trainee;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VacationRequest;
use App\Services\Notification\InboxPublisher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VacationRequestService
{
    private const RELATIONS = ['trainee.user', 'trainee.application.trainingProgram'];

    public function __construct(protected InboxPublisher $inbox) {}

    public function listForTrainee(Request $request)
    {
        abort_unless(AttendanceAccess::traineeId($request->user('api')), 403);

        return $this->list($request);
    }

    public function listForTrainer(Request $request)
    {
        abort_unless(AttendanceAccess::supervisorId($request->user('api')), 403);

        return $this->list($request);
    }

    public function list(Request $request)
    {
        $user = $request->user('api');
        $statuses = AttendanceAccess::isHr($user)
            ? [VacationRequestStatus::ACCEPTED->value]
            : array_column(VacationRequestStatus::cases(), 'value');
        $filters = AttendanceLists::filters($request, ['name', 'start_date', 'end_date', 'created_at', 'status'], [
            'status' => ['nullable', Rule::in($statuses)],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $trainees = AttendanceLists::filterTrainees(AttendanceAccess::trainees($user), $user, $filters);
        $query = $this->visible($user)->with(self::RELATIONS)
            ->whereIn('vacation_requests.trainee_id', $trainees->select('trainees.id'))
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->when($filters['date'] ?? null, fn (Builder $q, $date) => $q->overlapping($date, $date));

        return AttendanceLists::sort($query, $filters, 'created_at')->paginate(25)->withQueryString();
    }

    private function visible(User $user): Builder
    {
        return VacationRequest::query()->when(AttendanceAccess::isHr($user),
            fn (Builder $q) => $q->where('status', VacationRequestStatus::ACCEPTED));
    }

    public function create(User $user, array $data): VacationRequest
    {
        Gate::forUser($user)->authorize('create', VacationRequest::class);

        return DB::transaction(function () use ($user, $data) {
            $trainee = AttendanceEnrollment::lock($user->trainee->id);
            abort_unless(AttendanceEnrollment::isOpen($trainee, today()), 403, __('attendance.inactive_enrollment'));
            abort_unless($trainee->trainer, 422, __('attendance.no_supervisor'));

            $this->validateDates($data['start_date'], $data['end_date'], $trainee->application->trainingProgram);
            $this->ensureNoOverlap($trainee, $data['start_date'], $data['end_date']);

            $leave = VacationRequest::create([
                'trainee_id' => $trainee->id,
                'trainer_id' => $trainee->trainer_id,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'reason' => $data['reason'],
                'status' => VacationRequestStatus::PENDING,
            ]);

            $this->inbox->publish(
                $user, [$trainee->trainer->user_id], 'vacation_request_created', (string) $leave->id,
                'New Vacation Request', $user->name . ' has submitted a vacation request.',
                ['vacation_request_id' => $leave->id],
            );

            return $leave->setRelation('trainee', $trainee);
        }, 3);
    }

    public function find(int $requestId): VacationRequest
    {
        return $this->findForActor($requestId)->load(self::RELATIONS);
    }

    public function update(int $requestId, array $data): VacationRequest
    {
        $traineeId = $this->findForActor($requestId)->trainee_id;

        return DB::transaction(function () use ($requestId, $traineeId, $data) {
            $leave = $this->lockForMutation($requestId, $traineeId, 'update');

            $start = $data['start_date'] ?? $leave->start_date?->toDateString();
            $end = $data['end_date'] ?? $leave->end_date?->toDateString();
            $this->validateDates($start, $end, $leave->trainee->application->trainingProgram);
            $this->ensureNoOverlap($leave->trainee, $start, $end, $leave->id);
            $leave->update($data);

            return $leave;
        }, 3);
    }

    public function cancel(int $requestId, VacationRequestStatus $expected): VacationRequest
    {
        $traineeId = $this->findForActor($requestId)->trainee_id;

        return DB::transaction(function () use ($requestId, $traineeId, $expected) {
            $leave = $this->lockForMutation($requestId, $traineeId, 'delete');
            abort_unless($leave->status === $expected, 403, __('vacation_request.unauthorized_delete'));
            $leave->update(['status' => VacationRequestStatus::CANCELLED]);

            if ($expected === VacationRequestStatus::ACCEPTED) {
                $this->notifyDecision($leave, 'Vacation Request Cancelled', 'Your trainer has cancelled your approved vacation request.');
            }

            return $leave;
        }, 3);
    }

    public function decide(int $requestId, VacationRequestStatus $status): VacationRequest
    {
        [$title, $body] = match ($status) {
            VacationRequestStatus::ACCEPTED => ['Vacation Request Approved', 'Your trainer has approved your vacation request.'],
            VacationRequestStatus::REJECTED => ['Vacation Request Rejected', 'Your trainer has rejected your vacation request.'],
            default => throw ValidationException::withMessages(['status' => __('attendance.invalid_decision')]),
        };

        $traineeId = $this->findForActor($requestId)->trainee_id;

        return DB::transaction(function () use ($requestId, $traineeId, $status, $title, $body) {
            $leave = $this->lockForMutation($requestId, $traineeId, 'decide');
            if ($status === VacationRequestStatus::ACCEPTED) {
                $start = $leave->start_date?->toDateString();
                $end = $leave->end_date?->toDateString();
                $this->validateDates(
                    $start,
                    $end,
                    $leave->trainee->application->trainingProgram,
                    requireCurrentStart: false,
                );
                $this->ensureNoOverlap($leave->trainee, $start, $end, $leave->id);
            }
            $leave->update(['status' => $status]);
            $this->notifyDecision($leave, $title, $body);

            return $leave;
        }, 3);
    }

    private function findForActor(int $requestId): VacationRequest
    {
        $actor = auth('api')->user();
        $leave = $this->visible($actor)->findOrFail($requestId);
        Gate::forUser($actor)->authorize('view', $leave);

        return $leave;
    }

    private function lockForMutation(int $requestId, int $traineeId, string $ability): VacationRequest
    {
        $trainee = AttendanceEnrollment::lock($traineeId);
        $leave = VacationRequest::where('trainee_id', $traineeId)->lockForUpdate()->findOrFail($requestId);
        $leave->setRelation('trainee', $trainee);
        Gate::forUser(auth('api')->user())->authorize($ability, $leave);

        return $leave;
    }

    private function validateDates(?string $start, ?string $end, TrainingProgram $program, bool $requireCurrentStart = true): void
    {
        $errors = [];
        if ($start === null || $start < $program->start_date->toDateString()
            || ($requireCurrentStart && $start < today()->toDateString())) {
            $errors['start_date'] = $requireCurrentStart
                ? __('attendance.invalid_leave_start')
                : __('attendance.leave_start_outside_program');
        }
        if ($end === null || ($start !== null && $end < $start) || $end > $program->end_date->toDateString()) {
            $errors['end_date'] = __('attendance.invalid_leave_end');
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function ensureNoOverlap(Trainee $trainee, string $start, string $end, ?int $except = null): void
    {
        $conflict = $trainee->vacationRequests()
            ->whereIn('status', [VacationRequestStatus::PENDING, VacationRequestStatus::ACCEPTED])
            ->overlapping($start, $end)
            ->when($except, fn ($q) => $q->where('id', '!=', $except))
            ->lockForUpdate()->first(['id']);

        if ($conflict) {
            throw ValidationException::withMessages(['start_date' => __('attendance.overlapping_leave')]);
        }
    }

    private function notifyDecision(VacationRequest $leave, string $title, string $body): void
    {
        // Legacy non-terminal decisions may already occupy the old event key for this request.
        $status = $leave->status->value;
        $this->inbox->publish(
            auth('api')->user(), [$leave->trainee->user_id], 'vacation_request_decided', "{$leave->id}:{$status}",
            $title, $body, ['vacation_request_id' => $leave->id, 'status' => $status],
        );
    }
}
