<?php

namespace App\Services\Attendance;

use App\Enums\UserRole;
use App\Exceptions\ErrorException;
use App\Helpers\ApiCode;
use App\Models\Absence;
use App\Models\Application;
use App\Models\User;
use App\Models\VacationRequest;
use App\Services\Organization\TraineeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Enums\VacationRequestStatus;


class AbsenceService
{
    /**
     * Create a new class instance.
     */
    public function __construct(protected  TraineeService $traineeService)
    {
        //
    }


    public function allAbsences(Request $request)
    {
        $trainer = $request->user()->trainer;
        if ($trainer)
        {
            return Absence::where('trainer_id', $trainer->id)->with('trainer.user')->orderByDesc('date')
                ->get();
        }
        $trainee = $request->user()->trainee;
        if ($trainee)
        {
            return Absence::where('trainee_id', $trainee->id)->with('trainee.user')->orderByDesc('date')
                ->get();
        }

        if ($request->user()->roles()->where('role', UserRole::TRAINING_TEAM)->exists())
        {
            return Absence::filter($request->query())->with('trainee.user')->orderByDesc('date')
                ->get();
        }
        return Absence::query()->whereRaw('1 = 0')->get();
    }

    public function recordAbsence(int $trainee_id, ?string $reason, User $user)
    {
        $trainee=$this->traineeService->find($trainee_id);
        Gate::forUser($user)->authorize('record', [Absence::class , $trainee]);
        $existing_absence=Absence::where('trainee_id', $trainee_id)->where('trainer_id',$user->trainer->id)
            ->where('date',today())->first();
        if ($existing_absence)
        {
            throw new ErrorException(__('absence.already_exist'),ApiCode::BAD_REQUEST);
        }

        $today=today();
        $vacation_request = VacationRequest::where('trainee_id', $trainee_id)
            ->where('status', VacationRequestStatus::ACCEPTED)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->first();


        return Absence::create([
            'trainee_id' => $trainee_id,
            'trainer_id' => $user->trainer->id,
            'date' => $today,
            'reason' => $reason,
            'request_id' => $vacation_request?->id,
        ]);
//        return Absence::create(
//            [
//                'trainee_id' => $trainee_id,
//                'trainer_id'=>$user->trainer->id,
//                'date' => today(),
//                'reason' => $reason,
//            ]
//        );
    }
}
