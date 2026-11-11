<?php

namespace App\Http\Controllers\Dashboard;

use App\Helpers\ApiCode;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Dashboard\HrDashboardResource;
use App\Http\Resources\Dashboard\SupervisorDashboardResource;
use App\Http\Resources\Dashboard\TraineeDashboardResource;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponse;
    public function __construct( protected  DashboardService $dashboardService){}

    public function HR()
    {
        $dashboard=$this->dashboardService->HR();
        return $this->success(HrDashboardResource::make($dashboard));
    }

    public function trainer()
    {
        $dashboard = $this->dashboardService->trainer(auth('api')->user());
        return $this->success(SupervisorDashboardResource::make($dashboard));
    }

    public function trainee()
    {
        $dashboard = $this->dashboardService->trainee(auth('api')->user());
        return $this->success(TraineeDashboardResource::make($dashboard));
    }

    public function trainingCycle()
    {
        try {

            $report = $this->dashboardService->generateTrainingCycleReport();
            return $this->success($report);

        } catch (\Exception $exception) {
            return $this->error($exception->getMessage(), $exception->getCode());
        }
    }
}
