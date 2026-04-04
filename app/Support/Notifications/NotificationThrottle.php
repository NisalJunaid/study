<?php

namespace App\Support\Notifications;

use Illuminate\Support\Facades\Cache;

class NotificationThrottle
{
    public function shouldSend(string $key, int $cooldownHours): bool
    {
        if ($cooldownHours <= 0) {
            return true;
        }

        $cacheKey = 'notifications:cooldown:'.$key;

        if (Cache::has($cacheKey)) {
            return false;
        }

        Cache::put($cacheKey, true, now()->addHours($cooldownHours));

        return true;
    }
}
