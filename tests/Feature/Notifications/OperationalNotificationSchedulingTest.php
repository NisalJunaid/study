<?php

namespace Tests\Feature\Notifications;

use App\Jobs\Notifications\SendAdminOperationalAlertsJob;
use App\Jobs\Notifications\SendStudentRemindersJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class OperationalNotificationSchedulingTest extends TestCase
{
    public function test_operational_notification_command_dispatches_jobs(): void
    {
        Bus::fake();

        $this->artisan('notifications:dispatch-operational')
            ->assertSuccessful();

        Bus::assertDispatched(SendStudentRemindersJob::class);
        Bus::assertDispatched(SendAdminOperationalAlertsJob::class);
    }

    public function test_scheduler_registers_operational_notification_command(): void
    {
        $events = app(Schedule::class)->events();

        $commands = collect($events)
            ->map(fn ($event) => $event->command)
            ->filter();

        $this->assertTrue($commands->contains(fn (string $command) => str_contains($command, 'notifications:dispatch-operational')));
    }
}
