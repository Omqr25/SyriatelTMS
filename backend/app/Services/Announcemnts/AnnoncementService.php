<?php

namespace App\Services\Announcemnts;

use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\Application;
use App\Models\Trainee;
use App\Models\User;
use App\Services\Notification\InboxPublisher;
use App\Services\Training\TrainingProgramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnnoncementService
{
    /**
     * Create a new class instance.
     */
    public function __construct(protected TrainingProgramService $trainingProgramService, protected InboxPublisher $inbox)
    {
        //
    }

    public function trainerSendAnnouncement(User $sender, array $data)
    {
        $openProgram=$this->trainingProgramService->openProgram();
        $trainees=User::query()->whereRaw('1 = 0')->get();
        if ( $openProgram) {
            $trainees = User::query()
                ->whereHas('trainee', function ($query) use ($sender, $openProgram) {
                    $query->where('trainer_id', $sender->trainer?->id)
                        ->whereHas('application', function ($applicationQuery) use ($openProgram) {
                            $applicationQuery->where(
                                'training_program_id',
                                $openProgram->id
                            );
                        });
                })
                ->where('status', UserStatus::ACTIVE->value)
                ->get();
        }


        return $this->createAndNotify($sender, $data, $trainees);
    }

    public function hrSendAnnouncement(User $sender, array $data)
    {
        $openProgram=$this->trainingProgramService->openProgram();
        $trainees=User::query()->whereRaw('1 = 0')->get();
        if ( $openProgram) {
            $trainees = User::query()
                ->whereHas('trainee', function ($query) use ($sender, $openProgram) {
                    $query->whereHas('application', function ($applicationQuery) use ($openProgram) {
                            $applicationQuery->where('training_program_id', $openProgram->id);
                        });
                })
                ->where('status', UserStatus::ACTIVE->value)
                ->get();
        }

        return $this->createAndNotify($sender, $data, $trainees);
    }

    private function createAndNotify(User $sender, array $data, $trainees)
    {
        return DB::transaction(function () use ($sender, $data, $trainees) {
            $announcement = Announcement::create([
                'title' => $data['title'],
                'description' => $data['description'],
                'publisher_id' => $sender->id,
            ]);

            foreach ($trainees as $trainee) {
                $announcement->audiences()->create([
                    'audience_id' => $trainee->id,
                ]);
            }

            $this->inbox->publish(
                $sender, $trainees->modelKeys(), 'announcement', (string) $announcement->id,
                'New Announcement', 'A new announcement was published: ' . $announcement->title,
                ['announcement_id' => $announcement->id],
            );

            return $announcement->load('publisher')->loadCount('audiences');
        });
    }

    public function getForTrainee(Request $request ,User $trainee)
    {
        $announcements= Announcement::whereHas('audiences', function ($query) use ($trainee) {
                $query->where('audience_id', $trainee->id);
            })
            ->with(['publisher','traineeAudience'])
            ->latest()
            ->get();

        foreach ($announcements as $announcement) {
            if ($announcement->traineeAudience) {
                $announcement->traineeAudience->update(['read' => true]);
                $announcement->traineeAudience->read = true;
            }
        }
        return $announcements;
    }


    public function getSentByMe(Request $request ,User $user)
    {
        return Announcement::where('publisher_id',$user->id)
            ->with(['publisher','audiences.audience'])
            ->latest()
            ->get();

    }
}
