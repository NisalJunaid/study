<?php

namespace App\Console\Commands;

use App\Jobs\Notifications\SendAdminOperationalAlertsJob;
use App\Jobs\Notifications\SendStudentRemindersJob;
use Illuminate\Console\Command;

class DispatchOperationalNotificationsCommand extends Command
{
    protected $signature = 'notifications:dispatch-operational';

    protected $description = 'Dispatch student reminder and admin operational alert jobs';

    public function handle(): int
    {
        SendStudentRemindersJob::dispatch()->onQueue(config('study.queues.notifications', 'default'));
        SendAdminOperationalAlertsJob::dispatch()->onQueue(config('study.queues.notifications', 'default'));

        $this->info('Operational notification jobs dispatched.');

        return self::SUCCESS;
    }
}
