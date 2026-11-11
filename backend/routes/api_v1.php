<?php

use App\Http\Controllers\Announcemnts\AnnoncementController;
use App\Http\Controllers\Attendance\AbsenceController;
use App\Http\Controllers\Attendance\VacationRequestController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Evaluation\EvaluationController;
use App\Http\Controllers\Notification\DeviceTokenController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Organization\DepartmentController;
use App\Http\Controllers\Organization\TraineeController;
use App\Http\Controllers\Organization\TrainerController;
use App\Http\Controllers\Task\ProgramController;
use App\Http\Controllers\Task\TaskController;
use App\Http\Controllers\Training\ApplicationController;
use App\Http\Controllers\Training\TrainingProgramController;
use App\Jobs\TestQueueJob;
use App\Http\Resources\Dashboard\HrDashboardResource;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

Route::post('/debug-device-token', [
    \App\Http\Controllers\Notification\DeviceTokenController::class,
    'storeToken',
]);

Route::get('/health-check', function () {
    return response()->json([
        'status' => 'ok',
    ]);
});

Route::get('/test-email', function () {
    Mail::raw('This is a test email from Syriatel TMS.', function ($message) {
        $message->to('hudazain.ite@gmail.com')
            ->subject('Syriatel TMS Email Test');
    });

    return response()->json([
        'code' => 200,
        'message' => 'Email sent successfully.',
        'data' => null,
    ]);
});

Route::get('/test-queue', function () {
    TestQueueJob::dispatch();

    return response()->json([
        'message' => 'Queue job dispatched',
        'time' => now()->toDateTimeString(),
    ]);
});

Route::get('/testNotification', [NotificationController::class, 'sendNotification']);
Route::prefix('v1')->group(function () {


    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('forgotPassword', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
    });

    Route::prefix('training_program')->group(function () {
        Route::get('/', [TrainingProgramController::class, 'findAll']);
        Route::get('openProgram', [TrainingProgramController::class, 'openProgram']);
        Route::get('{training_program_id}', [TrainingProgramController::class, 'find']);
        Route::get('{training_program_id}/checkProgramStatus', [TrainingProgramController::class, 'checkProgramStatus']);
    });






    Route::middleware('auth:api')->group(function () {



        Route::prefix('auth')->group(function () {
            Route::get('logout', [AuthController::class, 'logout']);
            Route::post('changePassword', [AuthController::class, 'changePassword']);
        });

        Route::prefix('notification')->group(function () {
            Route::post('/device-tokens', [DeviceTokenController::class, 'storeToken']);
            Route::delete('/device-tokens', [DeviceTokenController::class, 'deleteToken']);
            Route::get('/', [NotificationController::class, 'index']);
            Route::patch('/{id}/read', [NotificationController::class, 'markAsRead']);

        });

        Route::prefix('absence')->group(function () {
            Route::get('findAll', [AbsenceController::class, 'findAll']);
        });


        Route::middleware('role:HR')->group(function () {
            Route::get('dashboard/hr', [DashboardController::class, 'HR']);
            Route::get('/reports/training_cycle', [DashboardController::class, 'trainingCycle',]);
            Route::get('evaluation/allEvaluations',[EvaluationController::class,'findAll']);
            Route::prefix('training_program')->group(function () {
                Route::post('/', [TrainingProgramController::class, 'store']);
                Route::put('{training_program_id}', [TrainingProgramController::class, 'update']);
                Route::delete('{training_program_id}', [TrainingProgramController::class, 'delete']);
            });

            Route::prefix('application')->group(function () {
                Route::get('/', [ApplicationController::class, 'findAll']);
                Route::get('assignedAndQualifiedApplications', [ApplicationController::class, 'assignedAndQualifiedApplications']);
                Route::get('interviewApplications', [ApplicationController::class, 'interviewApplications']);
                Route::get('activateTrainee/{application_id}', [ApplicationController::class, 'activateTrainee']);
                Route::get('{application_id}/assign/{trainer_id}', [ApplicationController::class, 'assignApplication']);
                Route::post('{application_id}/interview', [ApplicationController::class, 'scheduleInterview']);
                Route::get('acceptOrRejectApplicant/{application_id}/{type}', [ApplicationController::class, 'acceptOrRejectApplicant']);
                Route::delete('{application_id}', [ApplicationController::class, 'delete']);
            });

            Route::prefix('trainer')->group(function () {
                Route::get('/', [TrainerController::class, 'findAll']);
                Route::post('/', [TrainerController::class, 'store']);
                Route::get('{trainer_id}', [TrainerController::class, 'find']);
                Route::put('{trainer_id}', [TrainerController::class, 'update']);
                Route::delete('{trainer_id}', [TrainerController::class, 'delete']);
            });

            Route::prefix('department')->group(function () {
                Route::get('/', [DepartmentController::class, 'findAll']);
                Route::post('/', [DepartmentController::class, 'store']);
                Route::get('{department_id}', [DepartmentController::class, 'find']);
                Route::put('{department_id}', [DepartmentController::class, 'update']);
                Route::delete('{department_id}', [DepartmentController::class, 'delete']);
            });

            Route::prefix('trainee')->group(function () {
                Route::get('/', [TraineeController::class, 'findAll']);
                Route::post('fire/{trainee_id}', [TraineeController::class, 'fireTrainee']);
            });

            Route::prefix('announcement')->group(function () {
                Route::post('hrSendAnnouncement', [AnnoncementController::class, 'hrSendAnnouncement']);
            });

        });

        Route::middleware('role:SUPERVISOR')->group(function () {
            Route::get('dashboard/trainer', [DashboardController::class, 'trainer']);
            Route::prefix('application/trainer')->group(function () {
                Route::get('assigned', [ApplicationController::class, 'trainerApplications']);
                Route::get('interview', [ApplicationController::class, 'trainerInterviews']);
                Route::get('{application_id}/{type}', [ApplicationController::class, 'trainerAcceptOrRejectApplicant']);
                Route::patch('{interview_id}/interview/result', [ApplicationController::class, 'setInterviewResult']);
            });

            Route::prefix('program')->group(function () {
                Route::get('ownedPrograms', [ProgramController::class, 'ownedPrograms']);
                Route::post('/', [ProgramController::class, 'store']);
                Route::get('{program_id}', [ProgramController::class, 'find']);
                Route::put('{program_id}', [ProgramController::class, 'update']);
                Route::delete('{program_id}', [ProgramController::class, 'delete']);
            });

            Route::get('trainee/trainer', [TraineeController::class, 'myTrainees']);
            Route::prefix('task')->group(function () {
                Route::post('{milestone_id}/tasks', [TaskController::class, 'store']);
                Route::put('tasks/{task}', [TaskController::class, 'update']);
                Route::delete('tasks/{task}', [TaskController::class, 'delete']);
                Route::post('tasks/{task_id}/feedback', [TaskController::class, 'addTaskFeedback']);
            });

            Route::prefix('evaluation')->group(function () {
                Route::post('trainee/{trainee_id}', [EvaluationController::class, 'store']);
                Route::get('trainer/reevaluation_requests', [EvaluationController::class, 'reevaluationRequests']);
                Route::patch('reevaluation_requests/{request_id}/decision', [EvaluationController::class, 'decide']);
                Route::get('myEvaluations', [EvaluationController::class, 'myEvaluations']);
            });
            Route::prefix('vacation_request')->group(function () {
                Route::patch('decide/{request_id}',[VacationRequestController::class, 'decide']);
                Route::get('listForTrainer', [VacationRequestController::class, 'listForTrainer']);
            });

            Route::prefix('absence')->group(function () {
                Route::post('recordAbsence/{trainee_id}', [AbsenceController::class, 'recordAbsence']);
            });
            Route::prefix('announcement')->group(function () {
                Route::post('trainerSendAnnouncement', [AnnoncementController::class, 'trainerSendAnnouncement']);
            });

        });

        Route::middleware('role:HR,SUPERVISOR')->group(function () {
            Route::prefix('evaluation')->group(function () {
                Route::get('trainee/{trainee_id}', [EvaluationController::class, 'showTrainee']);
            });
            Route::prefix('trainee')->group(function () {
                Route::get('{trainee_id}', [TraineeController::class, 'find']);
            });

            Route::prefix('announcement')->group(function () {
                Route::get('getSentByMe', [AnnoncementController::class, 'getSentByMe']);
            });

        });

        Route::middleware('role:SUPERVISOR,TRAINEE')->group(function () {
            Route::prefix('task')->group(function () {
                Route::get('{milestone_id}/tasks', [TaskController::class, 'findAll']);
                Route::get('tasks/{task}', [TaskController::class, 'find']);
                Route::get('{evidence_id}/download', [TaskController::class, 'downloadEvidence'])->name('evidences.download');
            });
        });


        Route::middleware('role:TRAINEE')->group(function () {
            Route::get('dashboard/trainee', [DashboardController::class, 'trainee']);
            Route::prefix('task')->group(function () {
                Route::get('tasks/{task_id}/start', [TaskController::class, 'startTask']);
                Route::post('tasks/{task_id}/evidence', [TaskController::class, 'addEvidence']);
            });

            Route::prefix('evaluation')->group(function () {
                Route::get('me', [EvaluationController::class, 'myEvaluation']);
                Route::post('me/reevaluation_request', [EvaluationController::class, 'requestReevaluate']);
            });
             Route::get('myProgram', [ProgramController::class, 'myProgram']);

            Route::prefix('vacation_request')->group(function () {
                Route::post('/',[VacationRequestController::class, 'store']);
                Route::delete('/{request_id}',[VacationRequestController::class, 'cancel']);
                Route::get('listForTrainee', [VacationRequestController::class, 'listForTrainee']);
                Route::put('update/{request_id}', [VacationRequestController::class, 'update']);

            });

            Route::prefix('announcement')->group(function () {
                Route::get('traineeAnnouncements', [AnnoncementController::class, 'traineeAnnouncements']);
            });
            Route::get('/profile', [TraineeController::class, 'getProfile']);


        });

        Route::prefix('application')->group(function () {
            Route::get('{application_id}', [ApplicationController::class, 'find']);
        });

        Route::prefix('vacation_request')->group(function () {
            Route::get('find/{request_id}',[VacationRequestController::class, 'find']);
        });

    });
    Route::prefix('application')->group(function () {
        Route::post('/', [ApplicationController::class, 'store']);
        Route::get('{application_id}/attachments/{type}', [ApplicationController::class, 'downloadAttachment'])->name('applications.attachments.download');
    });

    Route::prefix('notification')->group(function (){
       Route::get('myNotifications',[NotificationController::class ,'myNotifications']);
       Route::get('sentByMe',[NotificationController::class,'index']);
    });

});
