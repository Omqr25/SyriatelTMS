<?php

namespace App\Services\Attendance;

use App\Enums\VacationRequestStatus;
use App\Exceptions\ErrorException;
use App\Models\User;
use App\Models\VacationRequest;
use App\Services\Notification\InboxPublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class VacationRequestService
{
    /**
     * Create a new class instance.
     */
    public function __construct(protected InboxPublisher $inbox)
    {
        //
    }

    public function listForTrainee(Request $request)
    {
        $trainee = $request->user('api')->trainee;
        return VacationRequest::where('trainee_id', $trainee->id)->latest()
            ->get();
    }

    public function listForTrainer(Request $request)
    {
        $trainer = $request->user('api')->trainer;
        return VacationRequest::where('trainer_id', $trainer->id)->latest()
            ->get() ;
    }

    public function create(User $user, array $data)
    {
        $trainee = $user->trainee;
        Gate::forUser($user)->authorize('create', VacationRequest::class);
        return DB::transaction(function () use ($user, $trainee, $data) {
            $vacation = VacationRequest::create([
                'trainee_id' => $trainee->id,
                'trainer_id' => $trainee->trainer_id,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'reason' => $data['reason'],
                'status' => VacationRequestStatus::PENDING->value,
            ]);

            $this->inbox->publish(
                $user, [$vacation->trainer->user_id], 'vacation_request_created', (string) $vacation->id,
                'New Vacation Request', $user->name . ' has submitted a vacation request.',
                ['vacation_request_id' => $vacation->id],
            );

            return $vacation;
        });
    }

    public function find(int $request_id)
    {
        $vacation_request=VacationRequest::find($request_id);
        if (! $vacation_request)
        {
            throw new ErrorException(__('vacation_request.not_found'));
        }
        Gate::forUser(auth('api')->user())->authorize('view', $vacation_request);
        return $vacation_request;
    }
    public function update(int $request_id, array $data)
    {
        $vacation_request = $this->find($request_id);
        Gate::forUser(auth('api')->user())->authorize('update', $vacation_request);
        $vacation_request->update($data);
        return $vacation_request->refresh();
    }

    public function cancel(int $request_id)
    {
        $vacation_request = $this->find($request_id);
        Gate::forUser(auth('api')->user())->authorize('delete', $vacation_request);
        $vacation_request->delete();
    }

    public function decide(int $request_id, string $status)
    {
        return DB::transaction(function () use ($request_id, $status) {
            $vacation_request = VacationRequest::lockForUpdate()->find($request_id) ?? $this->find($request_id);
            Gate::forUser(auth('api')->user())->authorize('decide', $vacation_request);
            $vacation_request->update(['status' => $status]);
            $vacation_request->refresh();
            $trainee=$vacation_request->trainee->user;
            if ($status === 'accepted') {
                $title = 'Vacation Request Approved';
                $body = 'Your trainer has approved your vacation request.';
            } else {
                $title = 'Vacation Request Rejected';
                $body = 'Your trainer has rejected your vacation request.';
            }
            $this->inbox->publish(
                auth('api')->user(), [$trainee->id], 'vacation_request_decided', (string) $vacation_request->id,
                $title, $body, ['vacation_request_id' => $vacation_request->id, 'status' => $status],
            );

            return $vacation_request;
        });
    }
}
