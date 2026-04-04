<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminAlertService;
use App\Services\Admin\AdminAnalyticsService;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(AdminAnalyticsService $analytics, AdminAlertService $adminAlertService): View
    {
        $metrics = $analytics->summarize();

        return view('pages.admin.dashboard', [
            'metrics' => $metrics,
            'oldestManualReviewAge' => $analytics->oldestManualReviewAge($metrics['operational']['oldest_manual_review_created_at']),
            'alerts' => $adminAlertService->currentAlerts(),
        ]);
    }
}
