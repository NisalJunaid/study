<?php

namespace App\Services\Admin;

use App\Models\Import;
use App\Models\PaymentSetting;
use App\Models\StudentAnswer;
use App\Models\SubscriptionPlan;

class AdminAlertService
{
    /** @return array<int,array<string,mixed>> */
    public function currentAlerts(): array
    {
        $alerts = [];

        $aiFailures = StudentAnswer::query()
            ->where('grading_status', StudentAnswer::STATUS_MANUAL_REVIEW)
            ->where('ai_result_json->manual_review_reason', 'ai_failed')
            ->where('updated_at', '>=', now()->subHours((int) config('study.notifications.admin.grading_failure_window_hours', 24)))
            ->count();

        if ($aiFailures > 0) {
            $alerts[] = [
                'key' => 'grading_failures',
                'level' => 'warning',
                'title' => 'Grading failures detected',
                'message' => "{$aiFailures} theory answer(s) recently fell back to manual review due to grading failures.",
            ];
        }

        $manualBacklog = StudentAnswer::query()
            ->where('grading_status', StudentAnswer::STATUS_MANUAL_REVIEW)
            ->count();

        $manualThreshold = (int) config('study.notifications.admin.manual_review_backlog_threshold', 20);
        if ($manualBacklog >= $manualThreshold) {
            $alerts[] = [
                'key' => 'manual_review_backlog',
                'level' => 'warning',
                'title' => 'Manual review backlog is high',
                'message' => "{$manualBacklog} answer(s) are waiting for manual review (threshold: {$manualThreshold}).",
            ];
        }

        $importFailures = Import::query()
            ->whereIn('status', [Import::STATUS_FAILED, Import::STATUS_PARTIALLY_COMPLETED])
            ->where('updated_at', '>=', now()->subHours((int) config('study.notifications.admin.import_failure_window_hours', 24)))
            ->count();

        if ($importFailures > 0) {
            $alerts[] = [
                'key' => 'import_failures',
                'level' => 'warning',
                'title' => 'Import failures need review',
                'message' => "{$importFailures} recent import(s) failed or partially completed.",
            ];
        }

        $setting = PaymentSetting::query()->first();
        $hasActivePlan = SubscriptionPlan::query()->active()->exists();

        if (! $setting || ! $hasActivePlan) {
            $alerts[] = [
                'key' => 'billing_configuration_anomaly',
                'level' => 'danger',
                'title' => 'Billing/access configuration anomaly',
                'message' => 'Billing access checks are missing payment settings or active plans. Student access may be blocked unexpectedly.',
            ];
        }

        return $alerts;
    }
}
