<?php

namespace App\Services\Announcemnts;

use App\Enums\TraineeStatus;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\AnnouncementAudience;
use App\Models\Notification;
use App\Models\User;
use App\Services\Notification\InboxPublisher;
use App\Services\Notification\NotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AnnoncementService
{
    public function __construct(
        protected InboxPublisher $inbox,
        protected NotificationService $notifications,
    ) {}

    public function trainerSendAnnouncement(User $sender, array $data)
    {
        abort_unless($sender->trainer, 422, __('announcements.no_recipients'));

        return $this->createAndNotify($sender, $data, $sender->trainer->id);
    }

    public function hrSendAnnouncement(User $sender, array $data)
    {
        return $this->createAndNotify($sender, $data);
    }

    private function eligibleRecipients(?int $trainerId = null, ?int $departmentId = null): Collection
    {
        return User::where('status', UserStatus::ACTIVE)
            ->whereHas('trainee', function (Builder $query) use ($trainerId, $departmentId) {
                $query->where('status', TraineeStatus::ACTIVE)
                    ->when($trainerId !== null, fn (Builder $query) => $query->where('trainer_id', $trainerId))
                    ->when($departmentId !== null, fn (Builder $query) => $query->where('department_id', $departmentId))
                    ->whereHas('application.trainingProgram', fn (Builder $query) => $query
                        ->whereNull('archived_at')->whereDate('end_date', '>=', today()));
            })
            ->pluck('id');
    }

    private function createAndNotify(User $sender, array $data, ?int $trainerId = null): Announcement
    {
        return DB::transaction(function () use ($sender, $data, $trainerId) {
            $departmentId = isset($data['department_id']) ? (int) $data['department_id'] : null;
            $recipientIds = $this->eligibleRecipients($trainerId, $departmentId);
            abort_if($recipientIds->isEmpty(), 422, __('announcements.no_recipients'));

            $announcement = Announcement::create([
                'title' => $data['title'],
                'description' => $data['description'],
                'publisher_id' => $sender->id,
                'department_id' => $departmentId,
            ]);
            $this->addAudience($announcement, $recipientIds);
            $this->publishAnnouncement($announcement, $sender, $recipientIds);

            return $announcement->load(['publisher', 'department'])->loadCount('audiences');
        }, 3);
    }

    public function update(User $sender, int $id, array $data): Announcement
    {
        return DB::transaction(function () use ($sender, $id, $data) {
            $announcement = $this->ownedAnnouncement($sender, $id);
            $previous = $announcement->audiences()->pluck('audience_id');
            $announcement->fill($data);
            if (! $announcement->isDirty()) {
                return $announcement->load(['publisher', 'department'])->loadCount('audiences');
            }

            $recipients = $announcement->isDirty('department_id')
                ? $this->eligibleRecipients(departmentId: $announcement->department_id)
                : $previous;
            abort_if($recipients->isEmpty() && $announcement->isDirty('department_id'), 422, __('announcements.no_recipients'));

            $added = $recipients->diff($previous);
            $removed = $previous->diff($recipients);
            $announcement->save();

            $announcement->audiences()->whereIn('audience_id', $removed)->delete();
            $this->addAudience($announcement, $added);

            $notification = Notification::where('event_key', "announcement:$id")->first();
            $notification?->update(['description' => $this->notificationDescription($announcement)]);
            $notification?->notified()->whereIn('notified_id', $removed)->delete();
            if ($added->isNotEmpty()) {
                $this->publishAnnouncement($announcement, $sender, $added);
            }

            // Existing and removed recipients only need to reload their current snapshot.
            $this->syncTrainees($sender, $previous);

            return $announcement->load(['publisher', 'department'])->loadCount('audiences');
        }, 3);
    }

    public function delete(User $sender, int $id): void
    {
        DB::transaction(function () use ($sender, $id) {
            $announcement = $this->ownedAnnouncement($sender, $id);
            $recipients = $announcement->audiences()->pluck('audience_id');
            Notification::where('event_key', "announcement:$id")->delete();
            $announcement->delete();
            $this->syncTrainees($sender, $recipients);
        }, 3);
    }

    private function ownedAnnouncement(User $sender, int $id): Announcement
    {
        return Announcement::where('publisher_id', $sender->id)->lockForUpdate()->findOrFail($id);
    }

    private function addAudience(Announcement $announcement, Collection $recipientIds): void
    {
        $announcement->audiences()->createMany(
            $recipientIds->map(fn (int $id) => ['audience_id' => $id])->all()
        );
    }

    private function publishAnnouncement(Announcement $announcement, User $sender, Collection $recipientIds): void
    {
        $this->inbox->publish(
            $sender, $recipientIds, 'announcement', (string) $announcement->id,
            'New Announcement', $this->notificationDescription($announcement),
            ['announcement_id' => $announcement->id],
        );
    }

    private function notificationDescription(Announcement $announcement): string
    {
        return 'A new announcement was published: '.$announcement->title;
    }

    private function syncTrainees(User $sender, Collection $recipients): void
    {
        if ($recipients->isNotEmpty()) {
            $this->inbox->publish(
                $sender, $recipients, Notification::ANNOUNCEMENT_SYNC, (string) Str::uuid(),
                'Announcement synchronization', '',
            );
        }
    }

    public function getForTrainee(User $trainee)
    {
        return DB::transaction(function () use ($trainee) {
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

            $this->notifications->markViewedAnnouncements($trainee, $announcements->modelKeys());

            return $announcements;
        });
    }

    public function getSentByMe(User $user)
    {
        return Announcement::where('publisher_id', $user->id)
            ->with(['publisher', 'department'])->withCount('audiences')
            ->latest()->orderByDesc('id')->get();
    }
}
