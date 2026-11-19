<?php

namespace App\Services\Announcemnts;

use App\Enums\TraineeStatus;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\AnnouncementAudience;
use App\Models\User;
use App\Services\Notification\InboxPublisher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AnnoncementService
{
    public function __construct(protected InboxPublisher $inbox) {}

    public function trainerSendAnnouncement(User $sender, array $data)
    {
        abort_unless($sender->trainer, 422, __('announcements.no_recipients'));

        return $this->createAndNotify($sender, $data, $sender->trainer->id);
    }

    public function hrSendAnnouncement(User $sender, array $data)
    {
        return $this->createAndNotify($sender, $data);
    }

    private function createAndNotify(User $sender, array $data, ?int $trainerId = null)
    {
        return DB::transaction(function () use ($sender, $data, $trainerId) {
            $recipientIds = User::where('status', UserStatus::ACTIVE)
                ->whereHas('trainee', function (Builder $query) use ($trainerId) {
                    $query->where('status', TraineeStatus::ACTIVE)
                        ->when($trainerId !== null, fn (Builder $query) => $query->where('trainer_id', $trainerId))
                        ->whereHas('application.trainingProgram', fn (Builder $query) => $query
                            ->whereDate('end_date', '>=', today()));
                })
                ->pluck('id');

            abort_if($recipientIds->isEmpty(), 422, __('announcements.no_recipients'));

            $announcement = Announcement::create([
                'title' => $data['title'],
                'description' => $data['description'],
                'publisher_id' => $sender->id,
            ]);

            $announcement->audiences()->createMany(
                $recipientIds->map(fn (int $id) => ['audience_id' => $id])->all()
            );

            $this->inbox->publish(
                $sender, $recipientIds, 'announcement', (string) $announcement->id,
                'New Announcement', 'A new announcement was published: ' . $announcement->title,
                ['announcement_id' => $announcement->id],
            );

            return $announcement->load('publisher')->loadCount('audiences');
        });
    }

    public function getForTrainee(User $trainee)
    {
        $announcements = Announcement::withWhereHas(
            'traineeAudience',
            fn ($query) => $query->where('audience_id', $trainee->id),
        )->with('publisher')->latest()->orderByDesc('id')->get();

        $unread = $announcements->pluck('traineeAudience')->where('read', false);
        if ($unread->isNotEmpty()) {
            AnnouncementAudience::where('audience_id', $trainee->id)
                ->whereKey($unread->pluck('id')->all())->update(['read' => true]);
            $unread->each(fn (AnnouncementAudience $audience) => $audience->read = true);
        }

        return $announcements;
    }

    public function getSentByMe(User $user)
    {
        return Announcement::where('publisher_id', $user->id)
            ->with('publisher')->withCount('audiences')
            ->latest()->orderByDesc('id')->get();
    }
}
