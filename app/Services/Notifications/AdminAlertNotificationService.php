<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Notifications\AdminOperationalAlertNotification;
use App\Services\Admin\AdminAlertService;
use App\Support\Notifications\NotificationThrottle;

class AdminAlertNotificationService
{
    public function __construct(
        private readonly AdminAlertService $adminAlertService,
        private readonly NotificationThrottle $throttle,
    ) {}

    public function sendAdminAlerts(): void
    {
        if (! config('study.notifications.enabled', true) || ! config('study.notifications.admin.enabled', true)) {
            return;
        }

        $alerts = $this->adminAlertService->currentAlerts();
        if ($alerts === []) {
            return;
        }

        $cooldownHours = max(1, (int) config('study.notifications.admin.cooldown_hours', 6));

        User::query()
            ->admins()
            ->chunkById(100, function ($admins) use ($alerts, $cooldownHours): void {
                foreach ($admins as $admin) {
                    $key = "admin:{$admin->id}:operational-alert";
                    if (! $this->throttle->shouldSend($key, $cooldownHours)) {
                        continue;
                    }

                    $admin->notify(new AdminOperationalAlertNotification($alerts));
                }
            });
    }
}
