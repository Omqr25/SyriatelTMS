<?php

namespace App\Services\Evaluation;


use App\Enums\EvaluationRequestStatus;
use App\Exceptions\ErrorException;
use App\Helpers\ApiCode;
use App\Http\Resources\Evaluation\EvaluationResource;
use App\Models\Evaluation;
use App\Models\Notification;
use App\Models\ReevaluationRequest;
use App\Models\Trainee;
use App\Models\User;
use App\Notifications\TmsPushNotification;
use App\Services\Notification\NotificationService;
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
    protected TrainingProgramService  $trainingProgramService,protected NotificationService $notificationService)
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
            $trainee=$evaluation->trainee->user;

            $notification = Notification::create([
                'title' => 'Evaluation Completed',
                'description' =>  'Your trainer has evaluated your performance. View your evaluation for details.',
                'notifier_id' =>auth('api')->id(),
            ]);

            $notification->notified()->create([
                'notified_id' => $trainee->id,
            ]);

            $trainee->notify(new TmsPushNotification(
                title: 'Evaluation Completed',
                body: 'Your trainer has evaluated your performance. View your evaluation for details.',
                data: [
                    'type' => 'evaluation_completed',
                    'evaluation_id' => $evaluation->id,
                ],
            ));
            $t=User::find(1);
            $t->notify(new TmsPushNotification(
                title: 'Evaluation Completed',
                body: 'Your trainer has evaluated your performance. View your evaluation for details.',
                data: [
                    'type' => 'evaluation_completed',
                    'evaluation_id' => $evaluation->id,
                ],
            ));
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
        $evaluation = Evaluation::with('criteria')
            ->where('trainee_id', $trainee->id)
            ->first();
        if (! $evaluation) {
            throw new ErrorException(__('evaluation.no_evaluation_for_trainee'),ApiCode::NOT_FOUND);
        }
        $reevaluation_request=ReevaluationRequest::where('trainee_id', $trainee->id)->where('evaluation_id',$evaluation->id)->first();
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
        $user = auth('api')->user();
        $evaluation=Evaluation::where('trainee_id', $user->trainee->id)->first();
        Gate::forUser($user)->authorize('requestReevaluation',$evaluation);
        $reevaluation_request= ReevaluationRequest::create([
                'trainee_id' => $user->trainee->id,
                'trainer_id' => $evaluation->trainer_id,
                'reason' => $reason,
                'evaluation_id' => $evaluation->id,
                'status' => EvaluationRequestStatus::PENDING,
            ]);

        // TODO: Notify the responsible supervisor.


        $trainer=$evaluation->trainer->user;
        $trainee=$evaluation->trainee;
        $notification = Notification::create([
            'title' => 'New Reevaluation Request',
            'description' => $trainee->user->name . ' has submitted an reevaluation request.',
            'notifier_id' =>auth('api')->id(),
        ]);

        $notification->notified()->create([
            'notified_id' => $trainer->id,
        ]);
        $trainer->notify(new TmsPushNotification(
            title: 'New Reevaluation Request',
            body: $trainee->user->name . ' has submitted an reevaluation request.',
            data: [
                'type' => 'revaluation_request_created',
                'reevaluation_request_id' => $reevaluation_request->id,
                'trainee_id' => $trainee->id,
            ],
        ));
        return $reevaluation_request;
    }

    public function decide(int $request_id, array $data)
    {
        $reevaluation = $this->findRequest($request_id);
        Gate::forUser(auth('api')->user())->authorize('decideReevaluation', $reevaluation);
        return DB::transaction(function () use ($reevaluation, $data){
             $reevaluation->update([
                'status' => $data['decision'],
                'decision_note' => $data['decision_note'],

            ]);
            $reevaluation->save();
            $evaluation=$reevaluation->evaluation;
            if ($data['decision'] == EvaluationRequestStatus::ACCEPTED->value)
            {
                $criterias = $data['indicators'];
                $score = collect($criterias)->avg(fn (array $criteria) => (float) $criteria['score']);
                Gate::forUser(auth('api')->user())->authorize('update', $evaluation);
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
                $trainee=$evaluation->trainee->user;
                // TODO: Notify the trainee.

                $notification = Notification::create([
                    'title' => 'Reevaluation Completed',
                    'description' => 'Your trainer has completed your reevaluation. View your updated result.',
                    'notifier_id' =>auth('api')->id(),
                ]);

                $notification->notified()->create([
                    'notified_id' => $trainee->id,
                ]);
                $trainee->notify(new TmsPushNotification(
                    title: 'Reevaluation Completed',
                    body: 'Your trainer has completed your reevaluation. View your updated result.',
                    data: [
                        'type' => 'reevaluation_completed',
                        'evaluation_id' => $evaluation->id,
                    ],
                ));

            }





            return $reevaluation->load('evaluation.criteria');
        });

    }
}
