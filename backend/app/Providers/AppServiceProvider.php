<?php

namespace App\Providers;

use App\Models\Interview;
use App\Models\Milestone;
use App\Models\ReevaluationRequest;
use App\Policies\EvaluationPolicy;
use App\Policies\InterviewPolicy;
use App\Policies\TaskPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        URL::forceRootUrl(config('app.url'));
        ResetPassword::createUrlUsing(function ($user, string $token) {
            return url('/reset-password')
                . '?token=' . urlencode($token)
                . '&email=' . urlencode($user->email);
        });
        Gate::policy(ReevaluationRequest::class, EvaluationPolicy::class);
        Gate::policy(Milestone::class, TaskPolicy::class);
    }

//    public function boot(): void
//    {
//        ResetPassword::createUrlUsing(function ($user, string $token) {
//            return rtrim(config('app.frontend_url'), '/')
//                . '/reset-password?token=' . urlencode($token)
//                . '&email=' . urlencode($user->email);
//        });
//    }
}
