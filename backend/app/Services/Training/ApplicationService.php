<?php

namespace App\Services\Training;

use App\Enums\ApplicationStatus;
use App\Enums\TraineeStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\ErrorException;
use App\Helpers\ApiCode;
use App\Jobs\SendAccountCreatedEmailJob;
use App\Jobs\SendInterviewEmailJob;
use App\Mail\ApplicationDecisionMail;
use App\Models\Application;
use App\Models\ApplicationAssign;
use App\Models\Interview;
use App\Models\Role;
use App\Models\Trainee;
use App\Models\Trainer;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Notification\InboxPublisher;
use App\Services\File\FileStorageService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class ApplicationService
{
    private const RESOURCE_RELATIONS = [
        'assigns.trainer.user.roles',
        'assigns.trainer.department',
    ];

    private function lockedApplication(int $id): Application
    {
        $application = Application::lockForUpdate()->find($id);
        if (! $application) {
            throw new ErrorException(
                __('application.not_found'),
                ApiCode::NOT_FOUND,
            );
        }

        return $application;
    }

    /**
     * Create a new class instance.
     */
    public function __construct(
        protected FileStorageService $fileStorageService,
        protected TrainingProgramService $trainingProgramService,
        protected InboxPublisher $inbox,
        protected ApplicationAnswerService $applicationAnswers,
    ) {
        //
    }

    public function create(array $data): Application
    {
        $formId = isset($data['application_form_id']) ? (int) $data['application_form_id'] : null;
        $answers = $data['answers'] ?? [];
        unset($data['application_form_id'], $data['answers']);

        $directory = 'applications/'.Str::uuid();
        $paths = [];
        $commitAttempted = false;

        try {
            foreach ([
                'cv' => 'cv',
                'national_id' => 'national-id',
                'academic_qualification' => 'qualifications',
                'personal_photo' => 'photo',
            ] as $field => $subdirectory) {
                $paths[$field] = isset($data[$field])
                    ? $this->fileStorageService->store($data[$field], "$directory/$subdirectory")
                    : null;
                if ($paths[$field] === '') {
                    throw new ErrorException(__('application_upload.server'));
                }
            }

            $application = DB::transaction(function () use ($data, $formId, $answers, $paths, &$commitAttempted) {
                $program = TrainingProgram::lockForUpdate()->findOrFail($data['training_program_id']);
                if ($program->phase() !== 'registration_open') {
                    throw new ErrorException(__('training_program.registration_closed'), 409, ['reason' => 'registration_closed']);
                }
                $form = $this->applicationAnswers->resolveForm($program, $formId, $answers);
                $values = $form ? $this->applicationAnswers->validate($form, $answers) : [];

                $program->registration_has_opened = true;
                $program->save();

                $application = Application::create(array_replace($data, $paths, [
                    'status' => ApplicationStatus::PENDING,
                    'application_form_id' => $form?->id,
                ]));
                $this->applicationAnswers->save($application, $values);
                $commitAttempted = true;

                return $application;
            });
        } catch (Throwable $error) {
            // An unacknowledged commit may still succeed. Keep its files for reconciliation.
            if (! $commitAttempted) {
                try {
                    $this->fileStorageService->deleteDirectory($directory);
                } catch (Throwable $cleanupError) {
                    report($cleanupError);
                }
            }

            throw $error;
        }

        return $application->fresh();
    }

    public function delete(int $application_id): void
    {
        $application = $this->find($application_id);
        if ($application->interviews()->exists()) {
            throw new ErrorException(
                __('application.delete_error'),
                ApiCode::BAD_REQUEST,
            );
        }

        $application->delete();
    }

    public function find(int $application_id): Application
    {
        $application = Application::find($application_id);
        if (! $application) {
            throw new ErrorException(
                __('application.not_found'),
                ApiCode::NOT_FOUND,
            );
        }

        return $application->load(self::RESOURCE_RELATIONS);
    }

    public function findAll(Request $request)
    {
        $options = $request->validate([
            'gpa_eligibility' => ['sometimes', 'required', 'string', Rule::in(['eligible', 'not_eligible'])],
            'english_eligibility' => ['sometimes', 'required', 'string', Rule::in(['eligible', 'not_eligible', 'pending'])],
            'sort_by' => ['sometimes', 'required', 'string', Rule::in(['name', 'gpa', 'english_test_score', 'status'])],
            'sort_direction' => ['sometimes', 'required', 'string', Rule::in(['asc', 'desc'])],
        ]);

        $query = Application::filter($request->query())
            ->whereNotIn('status', [
                ApplicationStatus::FIRED->value,
                ApplicationStatus::REJECTED->value,
                ApplicationStatus::COMPLETED->value,
            ])
            ->where('commitment', true)
            ->where('previous_training', false);

        $direction = $options['sort_direction'] ?? 'asc';
        switch ($options['sort_by'] ?? null) {
            case 'name':
                $query->orderBy('full_name', $direction);
                break;
            case 'gpa':
                $query->orderByRaw('CASE WHEN gpa IS NULL THEN 1 ELSE 0 END')->orderBy('gpa', $direction);
                break;
            case 'english_test_score':
                $query->orderByRaw('CASE WHEN english_test_score IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('english_test_score', $direction);
                break;
            case 'status':
                $workflow = [
                    ApplicationStatus::PENDING, ApplicationStatus::ASSIGNED, ApplicationStatus::QUALIFIED,
                    ApplicationStatus::FOR_INTERVIEW, ApplicationStatus::INTERVIEWED, ApplicationStatus::ACCEPTED,
                    ApplicationStatus::ACTIVE, ApplicationStatus::COMPLETED, ApplicationStatus::REJECTED,
                    ApplicationStatus::FIRED,
                ];
                $cases = implode(' ', array_map(fn ($rank) => "WHEN ? THEN $rank", array_keys($workflow)));
                $query->orderByRaw("CASE status $cases ELSE 99 END $direction", array_column($workflow, 'value'));
                break;
            default:
                $query->orderByDesc('created_at');
        }

        return $query
            ->orderByDesc('id')
            ->paginate(
                perPage: min(100, max(1, (int) $request->input('limit', 10))),
                page: $request->input('page', 1),
            );
    }

    public function assignedAndQualifiedApplications()
    {
        $training_program = $this->trainingProgramService->upcomingTrainingProgram();

        if (! $training_program) {
            return collect();
        }

        return Application::with([
            'latestAssignment.trainer.user.roles',
            'latestAssignment.trainer.department',
        ])
            ->where('training_program_id', $training_program->id)
            ->whereIn('status', [
                ApplicationStatus::ASSIGNED,
                ApplicationStatus::QUALIFIED,
            ])
            ->orderByDesc('created_at')
            ->get();
        //        return Application::where('status',ApplicationStatus::ASSIGNED)->orderByDesc('created_at')
        //            ->orWhere('status',ApplicationStatus::QUALIFIED)->get();
    }

    public function interviewApplications()
    {
        $training_program = $this->trainingProgramService->upcomingTrainingProgram();

        if (! $training_program) {
            return collect();
        }

        return Application::with([
            'latestInterview',
            'latestAssignment.trainer.user.roles',
            'latestAssignment.trainer.department',
        ])
            ->where('applications.training_program_id', $training_program->id)
            ->whereIn('applications.status', [
                ApplicationStatus::FOR_INTERVIEW,
                ApplicationStatus::INTERVIEWED,
                ApplicationStatus::ACCEPTED,
            ])
            ->join(
                'interviews',
                'applications.id',
                '=',
                'interviews.application_id',
            )
            ->select('applications.*')
            ->orderByRaw('CASE WHEN interviews.time >= ? THEN 0 ELSE 1 END', [
                now(),
            ])
            ->orderBy('interviews.time', 'asc')
            ->get();
        //        return Application::where('status',ApplicationStatus::FOR_INTERVIEW)
        //            ->orWhere('status',ApplicationStatus::INTERVIEWED)
        //            ->orWhere('status',ApplicationStatus::ACCEPTED)
        //            ->join('interviews', 'applications.id', '=', 'interviews.application_id')
        //            ->select('applications.*')
        //            ->orderByRaw(" CASE WHEN interviews.time >= ? THEN 0 ELSE 1 END ", [now()])
        //            ->orderBy('interviews.time', 'asc')
        //            ->get();
        //            ->orderBy('interviews.id', 'asc')
        //            ->get();
    }

    public function downloadApplicationAttachment($application_id, $type)
    {
        $application = $this->find($application_id);

        return $this->fileStorageService->downloadApplicationAttachment(
            $application,
            $type,
        );
    }

    public function assignApplication(int $application_id, int $trainer_id)
    {
        return DB::transaction(function () use ($application_id, $trainer_id) {
            $application = $this->lockedApplication($application_id);
            if ($application->status !== ApplicationStatus::PENDING) {
                throw new ErrorException(
                    __('application.assign_error'),
                    ApiCode::BAD_REQUEST,
                );
            }

            $application->update([
                'status' => ApplicationStatus::ASSIGNED,
            ]);
            ApplicationAssign::create([
                'application_id' => $application_id,
                'trainer_id' => $trainer_id,
                'status' => ApplicationStatus::ASSIGNED,
            ]);
            $application->fresh();

            return $application->load(self::RESOURCE_RELATIONS);
        });
    }

    public function trainerAcceptOrRejectApplicant(
        int $application_id,
        ApplicationStatus $type,
    ) {
        return DB::transaction(function () use ($application_id, $type) {
            $application = $this->lockedApplication($application_id);
            Gate::forUser(auth('api')->user())->authorize(
                'acceptOrReject',
                $application,
            );
            if (
                ! in_array(
                    $type,
                    [ApplicationStatus::ACCEPTED, ApplicationStatus::REJECTED],
                    true,
                ) ||
                $application->status !== ApplicationStatus::ASSIGNED
            ) {
                throw new ErrorException(
                    __('application.accept_reject_error'),
                    ApiCode::BAD_REQUEST,
                );
            }
            $application->update([
                'status' => $type == ApplicationStatus::ACCEPTED
                        ? ApplicationStatus::QUALIFIED
                        : ApplicationStatus::PENDING,
            ]);
            $application->fresh();

            return $application->load(self::RESOURCE_RELATIONS);
        });
    }

    public function scheduleInterview(int $application_id, array $data)
    {
        return DB::transaction(function () use ($application_id, $data) {
            $application = $this->lockedApplication($application_id);
            if ($application->status !== ApplicationStatus::QUALIFIED) {
                throw new ErrorException(
                    __('application.invalidStatusForInterviewQualified'),
                    ApiCode::BAD_REQUEST,
                );
            }

            // Serialize appointments for this supervisor before checking conflicts.
            $supervisor = Trainer::lockForUpdate()->findOrFail($data['trainer_id']);
            $interview_time = Carbon::parse($data['time']);
            $has_conflict = Interview::where('trainer_id', $data['trainer_id'])
                ->where('time', '>', $interview_time->copy()->subHour())
                ->where('time', '<', $interview_time->copy()->addHour())
                ->exists();

            if ($has_conflict) {
                throw new ErrorException(
                    __('application.trainerInterviewTimeConflict'),
                    ApiCode::BAD_REQUEST,
                );
            }

            $interview = Interview::create([
                'application_id' => $application_id,
                ...$data,
            ]);

            $application->update([
                'status' => ApplicationStatus::FOR_INTERVIEW,
            ]);

            // Each recipient retries independently: supervisor mail failure cannot
            // prevent the applicant from receiving their appointment details.
            foreach (['applicant', 'supervisor'] as $recipient) {
                SendInterviewEmailJob::dispatch($application, $interview, $recipient)->afterCommit();
            }
            $trainer = $supervisor->user;

            $this->inbox->publish(
                auth('api')->user(), [$trainer->id], 'interview', (string) $interview->id,
                'New interview', 'The training team scheduled an interview for you.',
                ['interview_id' => $interview->id, 'application_id' => $application_id],
            );

            return $interview->load(['trainer.user', 'application']);
        });
    }

    public function setInterviewResult(int $interview_id, array $data)
    {
        return DB::transaction(function () use ($interview_id, $data) {
            $interview = Interview::find($interview_id);
            if (! $interview) {
                throw new ErrorException(
                    __('application.interviewNotFound'),
                    ApiCode::NOT_FOUND,
                );
            }

            $user = auth('api')->user();

            Gate::forUser($user)->authorize('setResult', $interview);
            //        Gate::authorize('setResult', $interview);
            $application = $this->lockedApplication($interview->application_id);
            if ($application->status !== ApplicationStatus::FOR_INTERVIEW) {
                throw new ErrorException(
                    __('application.invalidStatusForResult'),
                    ApiCode::BAD_REQUEST,
                );
            }

            $interview->update([
                'evaluation' => $data['evaluation'],
                'interview_note' => $data['interview_note'] ?? null,
            ]);

            $application->update([
                'status' => ApplicationStatus::INTERVIEWED,
            ]);

            return $interview->load(['trainer.user', 'application']);
        });
    }

    public function acceptOrRejectApplicant(
        int $application_id,
        ApplicationStatus $type,
    ) {
        return DB::transaction(function () use ($application_id, $type) {
            $application = $this->lockedApplication($application_id);
            if (
                ! in_array(
                    $type,
                    [ApplicationStatus::ACCEPTED, ApplicationStatus::REJECTED],
                    true,
                ) ||
                ($type === ApplicationStatus::ACCEPTED &&
                    $application->status !== ApplicationStatus::INTERVIEWED) ||
                in_array(
                    $application->status,
                    [
                        ApplicationStatus::ACCEPTED,
                        ApplicationStatus::REJECTED,
                        ApplicationStatus::COMPLETED,
                        ApplicationStatus::FIRED,
                        ApplicationStatus::ACTIVE,
                    ],
                    true,
                )
            ) {
                throw new ErrorException(
                    __('application.application_status_cant_change'),
                    ApiCode::BAD_REQUEST,
                );
            }

            $application->update([
                'status' => $type == ApplicationStatus::ACCEPTED
                        ? ApplicationStatus::ACCEPTED
                        : ApplicationStatus::REJECTED,
            ]);
            $application->fresh();
            $decisionMail = new ApplicationDecisionMail($application);
            $decisionMail->afterCommit();
            Mail::to($application->email)->queue($decisionMail);

            return $application->load(self::RESOURCE_RELATIONS);
        });
    }

    public function activateTrainee(int $application_id)
    {
        return DB::transaction(function () use ($application_id) {
            $application = $this->lockedApplication($application_id);
            if ($application->status !== ApplicationStatus::ACCEPTED) {
                throw new ErrorException(
                    __('application.activateError'),
                    ApiCode::BAD_REQUEST,
                );
            }
            $trainer = $application->assigns()->latest('id')->first()?->trainer;
            $department = $trainer?->department;
            if (
                ! $trainer ||
                ! $department ||
                $application->trainee()->exists()
            ) {
                throw new ErrorException(
                    __('application.activateError'),
                    ApiCode::BAD_REQUEST,
                );
            }

            $password = Str::password(8);

            $user = User::create([
                'name' => $application->full_name,
                'email' => $application->email,
                'phone_number' => $application->phone_number,
                'password' => $password,
                'status' => UserStatus::ACTIVE,
            ]);

            $traineeRole = Role::where('role', UserRole::TRAINEE)->first();
            $user->userRoles()->create([
                'role_id' => $traineeRole->id,
                'assigned_at' => now(),
            ]);
            Trainee::create([
                'user_id' => $user->id,
                'application_id' => $application->id,
                'department_id' => $department->id,
                'trainer_id' => $trainer->id,
                'status' => TraineeStatus::ACTIVE,
            ]);
            $application->update(['status' => ApplicationStatus::ACTIVE]);
            SendAccountCreatedEmailJob::dispatch(
                user: $user,
                password: $password,
                role: 'Trainee',
            )->afterCommit();

            return $application->fresh()->load(self::RESOURCE_RELATIONS);
        });
    }

    public function trainerApplications(Trainer $trainer)
    {
        $training_program = $this->trainingProgramService->upcomingTrainingProgram();

        if (! $training_program) {
            return collect();
        }

        return $trainer
            ->applications()
            ->where('applications.training_program_id', $training_program->id)
            ->where('applications.status', ApplicationStatus::ASSIGNED)
            ->with('assigns.trainer.user')
            ->latest('applications.created_at')
            ->get();
        //        return $trainer->applications()->latest()->get()->where('status', ApplicationStatus::ASSIGNED);
    }

    public function trainerInterviews(Trainer $trainer)
    {
        $training_program = $this->trainingProgramService->upcomingTrainingProgram();
        if (! $training_program) {
            return collect();
        }

        return $trainer
            ->interviews()
            ->whereHas('application', function ($query) use (
                $training_program,
            ) {
                $query->where('training_program_id', $training_program->id);
            })
            ->with(['trainer.user', 'application'])
            ->latest()
            ->get();
        //        $interviews=  $trainer->interviews()->latest()->get();
        //        $applications=  $interviews->map(function($interview){
        //            return $interview->load(['trainer.user','application']);
        //        });
        //        return $applications;
    }
}
