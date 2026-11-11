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

    public function traineeAnnouncements(Request $request)
    {
        $announcements = $this->annoncementService->getForTrainee($request ,$request->user('api'));
        return $this->success(AnnouncemntResource::collection($announcements));
    }


    public function getSentByMe(Request $request)
    {
        $announcements = $this->annoncementService->getSentByMe($request,request()->user('api'));
        return $this->success(AnnouncemntResource::collection($announcements));

    }

}
