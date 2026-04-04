<?php

namespace Tests\Feature\Notifications;

use App\Models\Quiz;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Notifications\StudentDraftQuizReminderNotification;
use App\Notifications\StudentInactivityReminderNotification;
use App\Notifications\StudentPendingVerificationReminderNotification;
use App\Services\Notifications\StudentReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StudentReminderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_reminders_are_generated_for_eligible_students(): void
    {
        Notification::fake();
        Cache::flush();

        config()->set('study.notifications.student.inactivity.days', 7);
        config()->set('study.notifications.student.inactivity.cooldown_hours', 24);
        config()->set('study.notifications.student.pending_verification.minimum_pending_hours', 12);
        config()->set('study.notifications.student.pending_verification.cooldown_hours', 24);
        config()->set('study.notifications.student.draft_quiz.inactive_minutes', 60);
        config()->set('study.notifications.student.draft_quiz.cooldown_hours', 24);

        $inactiveStudent = User::factory()->student()->create();

        $pendingStudent = User::factory()->student()->create();
        SubscriptionPayment::query()->create([
            'user_id' => $pendingStudent->id,
            'subscription_plan_id' => null,
            'user_subscription_id' => null,
            'amount' => 12,
            'currency' => 'USD',
            'status' => SubscriptionPayment::STATUS_PENDING,
            'submitted_at' => now()->subHours(18),
            'temporary_access_expires_at' => now()->addHours(2),
            'temporary_quiz_limit' => 6,
        ]);

        $draftStudent = User::factory()->student()->create();
        Quiz::query()->create([
            'user_id' => $draftStudent->id,
            'level' => 'o_level',
            'mode' => Quiz::MODE_MCQ,
            'status' => Quiz::STATUS_DRAFT,
            'total_questions' => 10,
            'total_possible_score' => 10,
            'started_at' => now()->subHours(3),
            'last_interacted_at' => now()->subHours(2),
        ]);

        app(StudentReminderService::class)->sendStudentReminders();

        Notification::assertSentTo($inactiveStudent, StudentInactivityReminderNotification::class);
        Notification::assertSentTo($pendingStudent, StudentPendingVerificationReminderNotification::class);
        Notification::assertSentTo($draftStudent, StudentDraftQuizReminderNotification::class);
    }

    public function test_student_reminders_do_not_repeat_within_cooldown_window(): void
    {
        Notification::fake();
        Cache::flush();

        config()->set('study.notifications.student.inactivity.days', 1);
        config()->set('study.notifications.student.inactivity.cooldown_hours', 24);

        $student = User::factory()->student()->create();

        $service = app(StudentReminderService::class);
        $service->sendStudentReminders();
        $service->sendStudentReminders();

        Notification::assertSentToTimes($student, StudentInactivityReminderNotification::class, 1);
    }
}
