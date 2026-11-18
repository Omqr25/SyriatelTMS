<?php

namespace App\Services\Evaluation;


use App\Enums\EvaluationRequestStatus;
use App\Enums\UserRole;
use App\Exceptions\ErrorException;
use App\Helpers\ApiCode;
use App\Http\Resources\Evaluation\EvaluationResource;
use App\Models\Evaluation;
use App\Models\ReevaluationRequest;
use App\Models\Trainee;
use App\Models\User;
use App\Services\Notification\InboxPublisher;
use App\Services\Organization\TraineeService;
use App\Services\Organization\TrainerService;
use App\Services\Training\TrainingProgramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class EvaluationService
{
    /**
     * Create a new class instance.
     */
    public function __construct(protected TraineeService $traineeService , protected  TrainerService $trainerService,
    protected TrainingProgramService  $trainingProgramService,protected InboxPublisher $inbox)
    {
        //
    }

    public function findAll(Request $request)
    {
        return  Evaluation::with(['criteria','trainer','trainee.department'])->orderBy('created_at', 'desc')
           ->get();
    }
    public function create(User $user, int $trainee_id, array $data)
    {
        return DB::transaction(function () use ($user, $trainee_id, $data)
        {
            Trainee::whereKey($trainee_id)->lockForUpdate()->first();
            Gate::forUser($user)->authorize('create', [Evaluation::class, $trainee_id]);
            $criterias = $data['indicators'];
            $score = collect($criterias)->avg(fn (array $criteria) => (float) $criteria['score']);
            $evaluation=Evaluation::create([
                'trainee_id' => $trainee_id,
                'trainer_id' => $user->trainer->id,
                'score' => round($score, 2),
                'description' => $data['description'] ?? null,
            ]);
            $evaluation->save();
            foreach ($criterias as $criteria) {
                $evaluation->criteria()->create([
                    'name' => $criteria['name'],
                    'score' => $criteria['score'],
                    'notice' => $criteria['notice'] ?? null,
                ]);
            }
            $recipients = User::whereHas('roles', fn ($query) => $query->where('role', UserRole::TRAINING_TEAM->value))
                ->pluck('id')->push($evaluation->trainee->user_id)->unique();
            $this->inbox->publish(
                $user, $recipients, 'evaluation_completed', (string) $evaluation->id,
                'Evaluation Completed', 'A trainee evaluation has been completed.',
                ['evaluation_id' => $evaluation->id],
            );

            return $evaluation->load('criteria');
        });
    }

    public function showTrainee(int $trainee_id)
    {
        Gate::forUser(auth('api')->user())->authorize('viewAny',[Evaluation::class, $trainee_id]);
        return Evaluation::with('criteria')
            ->where('trainee_id', $trainee_id)
            ->get();
    }

    public function reevaluationRequests(Request $request)
    {
        $training_program=$this->trainingProgramService->openProgram();
        $trainer=$request->user('api')->trainer;
        return ReevaluationRequest::with(['trainee', 'evaluation.criteria'])
            ->whereHas('trainee.application', function ($query) use ($training_program) {
                $query->where('training_program_id', $training_program?->id);
            })->where('trainer_id', $trainer->id)->where('status',EvaluationRequestStatus::PENDING->value)
            ->orderBy('created_at','desc')->get();
    }

    public function findRequest(int $request_id)
    {
        $reevaluation_request=ReevaluationRequest::find($request_id);
        if (!$reevaluation_request) {
            throw new ErrorException(__('evaluation.reevaluation_request_not_found'));
        }
        return $reevaluation_request;
    }

    public function myEvaluation(User $user)
    {
        $trainee = $user->trainee;
        if (! $trainee) {
            throw new ErrorException(__('evaluation.no_evaluation_yet'), ApiCode::NOT_FOUND);
        }
        $evaluation = Evaluation::with('criteria')
            ->where('trainee_id', $trainee->id)
            ->first();
        if (! $evaluation) {
            throw new ErrorException(__('evaluation.no_evaluation_yet'), ApiCode::NOT_FOUND);
        }
        $reevaluation_request=ReevaluationRequest::where('trainee_id', $trainee->id)->first();
        Gate::forUser($user)->authorize('view', $evaluation);
        return [$evaluation , (bool)$reevaluation_request];
    }

    public function myEvaluations(User $user)
    {
        $training_program=$this->trainingProgramService->openProgram();
        $trainer = $user->trainer;
        return  Evaluation::with('criteria')
            ->where('trainer_id', $trainer->id)
            ->whereHas('trainee.application', function ($query) use ($training_program) {
                $query->where('training_program_id', $training_program->id);})
            ->orderBy('created_at', 'desc')->get();

    }

    public function requestReevaluate(string $reason)
    {
        return DB::transaction(function () use ($reason) {
            $user = auth('api')->user();
            if ($user->trainee) {
                Trainee::whereKey($user->trainee->id)->lockForUpdate()->first();
            }
            $evaluation = $user->trainee
                ? Evaluation::where('trainee_id', $user->trainee->id)->first()
                : null;
            // Without this guard a null evaluation reaches the Gate and fails with a generic 403.
            if (! $evaluation) {
                throw new ErrorException(__('evaluation.no_evaluation_for_trainee'), ApiCode::NOT_FOUND);
            }
            Gate::forUser($user)->authorize('requestReevaluation',$evaluation);
            $reevaluation_request= ReevaluationRequest::create([
                    'trainee_id' => $user->trainee->id,
                    'trainer_id' => $evaluation->trainer_id,
                    'reason' => $reason,
                    'evaluation_id' => $evaluation->id,
                    'status' => EvaluationRequestStatus::PENDING,
                ]);

            $this->inbox->publish(
                $user, [$evaluation->trainer->user_id], 'reevaluation_request_created', (string) $reevaluation_request->id,
                'New Reevaluation Request', $user->name . ' has submitted a reevaluation request.',
                ['reevaluation_request_id' => $reevaluation_request->id],
            );
            return $reevaluation_request;
        });
    }

    public function decide(int $request_id, array $data)
    {
        return DB::transaction(function () use ($request_id, $data) {
            $reevaluation = ReevaluationRequest::lockForUpdate()->find($request_id)
                ?? $this->findRequest($request_id);
            $user = auth('api')->user();
            Gate::forUser($user)->authorize('decideReevaluation', $reevaluation);
            $reevaluation->update([
                'status' => $data['decision'],
                'decision_note' => $data['decision_note'],
            ]);
            $evaluation = $reevaluation->evaluation;
            if ($data['decision'] === EvaluationRequestStatus::ACCEPTED->value) {
                $criterias = $data['indicators'];
                $score = collect($criterias)->avg(fn (array $criteria) => (float) $criteria['score']);
                Gate::forUser($user)->authorize('update', $evaluation);
                $evaluation->update([
                    'score' => round($score, 2),
                    'description' => $data['description'] ?? $evaluation->description,
                ]);
                $evaluation->save();
                $evaluation->criteria()->delete();
                foreach ($criterias as $criteria) {
                    $evaluation->criteria()->create([
                        'name' => $criteria['name'],
                        'score' => $criteria['score'],
                        'notice' => $criteria['notice'] ?? null,
                    ]);
                }
                $this->inbox->publish(
                    $user, [$evaluation->trainee->user_id], 'reevaluation_completed', (string) $reevaluation->id,
                    'Reevaluation Completed', 'Your trainer has completed your reevaluation. View your updated result.',
                    ['evaluation_id' => $evaluation->id],
                );
            }

            return $reevaluation->load('evaluation.criteria');
        });

    }
}
