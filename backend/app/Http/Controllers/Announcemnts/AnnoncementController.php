<?php

namespace App\Http\Controllers\Announcemnts;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Announcemnts\StoreAnnoncementRequest;
use App\Http\Resources\Announcemnt\AnnouncemntResource;
use App\Services\Announcemnts\AnnoncementService;
use Illuminate\Http\Request;

class AnnoncementController extends Controller
{

    use ApiResponse;
    public function __construct( protected  AnnoncementService $annoncementService)
    {

    }
    public function trainerSendAnnouncement(StoreAnnoncementRequest $request)
    {
        $announcement = $this->annoncementService->trainerSendAnnouncement($request->user(), $request->validated());
        return $this->success(AnnouncemntResource::make($announcement));
    }

    public function hrSendAnnouncement(StoreAnnoncementRequest $request)
    {
        $announcement = $this->annoncementService->hrSendAnnouncement($request->user(), $request->validated());
        return $this->success(AnnouncemntResource::make($announcement));
    }

    public function update(StoreAnnoncementRequest $request, int $announcement)
    {
        $updated = $this->annoncementService->update($request->user(), $announcement, $request->validated());

        return $this->success(AnnouncemntResource::make($updated), __('announcements.updated'));
    }

    public function destroy(Request $request, int $announcement)
    {
        $this->annoncementService->delete($request->user(), $announcement);

        return $this->success(null, __('announcements.deleted'));
    }

    public function traineeAnnouncements(Request $request)
    {
        $announcements = $this->annoncementService->getForTrainee($request->user());
        return $this->success(AnnouncemntResource::collection($announcements));
    }


    public function getSentByMe(Request $request)
    {
        $announcements = $this->annoncementService->getSentByMe($request->user());
        return $this->success(AnnouncemntResource::collection($announcements));

    }

}
