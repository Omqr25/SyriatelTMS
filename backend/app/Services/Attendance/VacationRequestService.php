<?php

namespace App\Services\Attendance;

use App\Enums\VacationRequestStatus;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VacationRequest;
use App\Services\Notification\InboxPublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class VacationRequestService
{
    private const RELATIONS = ['trainee.user', 'trainee.application.trainingProgram'];

    public function __construct(protected InboxPublisher $inbox) {}

    public function listForTrainee(Request $request)
    {
        $trainee = $request->user('api')->trainee;
        abort_unless($trainee, 403);

        return VacationRequest::where('trainee_id', $trainee->id)->with(self::RELATIONS)
            ->latest()->orderByDesc('id')->paginate(25)->withQueryString();
    }

    public function listForTrainer(Request $request)
    {
        $trainer = $request->user('api')->trainer;
        abort_unless($trainer, 403);

        return VacationRequest::where('trainer_id', $trainer->id)->with(self::RELATIONS)
            ->latest()->orderByDesc('id')->paginate(25)->withQueryString();
    }

    public function create(User $user, array $data): VacationRequest
    {
        Gate::forUser($user)->authorize('create', VacationRequest::class);

        return DB::transaction(function () use ($user, $data) {
            $trainee = AttendanceEnrollment::lock($user->trainee->id);
            abort_unless(AttendanceEnrollment::isOpen($trainee, today()), 403, __('attendance.inactive_enrollment'));
            abort_unless($trainee->trainer, 422, __('attendance.no_supervisor'));

            $this->validateDates($data['start_date'], $data['end_date'], $trainee->application->trainingProgram);

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

            $this->validateDates(
                $data['start_date'] ?? $leave->start_date?->toDateString(),
                $data['end_date'] ?? $leave->end_date?->toDateString(),
                $leave->trainee->application->trainingProgram,
            );
            $leave->update($data);

            return $leave;
        }, 3);
    }

    public function cancel(int $requestId): void
    {
        $traineeId = $this->findForActor($requestId)->trainee_id;

        DB::transaction(function () use ($requestId, $traineeId) {
            $this->lockForMutation($requestId, $traineeId, 'delete')->delete();
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
                $this->validateDates(
                    $leave->start_date?->toDateString(),
                    $leave->end_date?->toDateString(),
                    $leave->trainee->application->trainingProgram,
                    requireFutureStart: false,
                );
            }
            $leave->update(['status' => $status]);
            // Legacy non-terminal decisions may already occupy the old event key for this request.
            $this->inbox->publish(
                auth('api')->user(), [$leave->trainee->user_id], 'vacation_request_decided', "{$leave->id}:{$status->value}",
                $title, $body, ['vacation_request_id' => $leave->id, 'status' => $status->value],
            );

            return $leave;
        }, 3);
    }

    private function findForActor(int $requestId): VacationRequest
    {
        $leave = VacationRequest::findOrFail($requestId);
        Gate::forUser(auth('api')->user())->authorize('view', $leave);

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

    private function validateDates(?string $start, ?string $end, TrainingProgram $program, bool $requireFutureStart = true): void
    {
        $errors = [];
        if ($start === null || $start < $program->start_date->toDateString()
            || ($requireFutureStart && $start <= today()->toDateString())) {
            $errors['start_date'] = $requireFutureStart
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
}
