<?php

namespace App\Providers;

use App\Services\Billing\AiCreditQuotaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

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
        View::composer('layouts.app-shell', function ($view): void {
            $user = Auth::user();

            if (! $user) {
                $view->with('activeSubscription', null);
                $view->with('showSuspensionOverlay', false);
                $view->with('aiCredits', null);

                return;
            }

            if ($user->isAdmin()) {
                $view->with('activeSubscription', null);
                $view->with('showSuspensionOverlay', false);
                $view->with('aiCredits', null);

                return;
            }

            $user->loadMissing('currentSubscription');
            $activeSubscription = $user->currentSubscription;
            $aiCredits = app(AiCreditQuotaService::class)->summaryForUser($user);

            $view->with('activeSubscription', $activeSubscription);
            $view->with('showSuspensionOverlay', (bool) $activeSubscription?->isSuspended());
            $view->with('aiCredits', $aiCredits);
        });
    }
}
