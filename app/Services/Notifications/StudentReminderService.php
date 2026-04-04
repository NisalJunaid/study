<?php

namespace App\Services\Notifications;

use App\Models\Quiz;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Notifications\StudentDraftQuizReminderNotification;
use App\Notifications\StudentInactivityReminderNotification;
use App\Notifications\StudentPendingVerificationReminderNotification;
use App\Support\Notifications\NotificationThrottle;

class StudentReminderService
{
    public function __construct(
        private readonly NotificationThrottle $throttle,
    ) {}

    public function sendStudentReminders(): void
    {
        if (! config('study.notifications.enabled', true) || ! config('study.notifications.student.enabled', true)) {
            return;
        }

        if (config('study.notifications.student.inactivity.enabled', true)) {
            $this->sendInactivityReminders();
        }

        if (config('study.notifications.student.pending_verification.enabled', true)) {
            $this->sendPendingVerificationReminders();
        }

        if (config('study.notifications.student.draft_quiz.enabled', true)) {
            $this->sendDraftQuizReminders();
        }
    }

    private function sendInactivityReminders(): void
    {
        $inactiveDays = max(1, (int) config('study.notifications.student.inactivity.days', 7));
        $cooldownHours = max(1, (int) config('study.notifications.student.inactivity.cooldown_hours', 24));
        $cutoff = now()->subDays($inactiveDays);

        User::query()
            ->students()
            ->where(function ($query) use ($cutoff): void {
                $query->whereDoesntHave('quizzes')
                    ->orWhereDoesntHave('quizzes', function ($quizQuery) use ($cutoff): void {
                        $quizQuery->where(function ($activityQuery) use ($cutoff): void {
                            $activityQuery
                                ->where('created_at', '>', $cutoff)
                                ->orWhere('submitted_at', '>', $cutoff)
                                ->orWhere('last_interacted_at', '>', $cutoff);
                        });
                    });
            })
            ->chunkById(100, function ($students) use ($cooldownHours, $inactiveDays): void {
                foreach ($students as $student) {
                    $key = "student:{$student->id}:inactivity";
                    if (! $this->throttle->shouldSend($key, $cooldownHours)) {
                        continue;
                    }

                    $student->notify(new StudentInactivityReminderNotification($inactiveDays));
                }
            });
    }

    private function sendPendingVerificationReminders(): void
    {
        $cooldownHours = max(1, (int) config('study.notifications.student.pending_verification.cooldown_hours', 24));
        $pendingHours = max(1, (int) config('study.notifications.student.pending_verification.minimum_pending_hours', 12));

        User::query()
            ->students()
            ->whereHas('payments', function ($query) use ($pendingHours): void {
                $query->where('status', SubscriptionPayment::STATUS_PENDING)
                    ->where('submitted_at', '<=', now()->subHours($pendingHours));
            })
            ->chunkById(100, function ($students) use ($cooldownHours): void {
                foreach ($students as $student) {
                    $key = "student:{$student->id}:pending-verification";
                    if (! $this->throttle->shouldSend($key, $cooldownHours)) {
                        continue;
                    }

                    $student->notify(new StudentPendingVerificationReminderNotification());
                }
            });
    }

    private function sendDraftQuizReminders(): void
    {
        $cooldownHours = max(1, (int) config('study.notifications.student.draft_quiz.cooldown_hours', 24));
        $inactiveMinutes = max(5, (int) config('study.notifications.student.draft_quiz.inactive_minutes', 120));
        $cutoff = now()->subMinutes($inactiveMinutes);

        User::query()
            ->students()
            ->whereHas('quizzes', function ($query) use ($cutoff): void {
                $query
                    ->whereIn('status', [Quiz::STATUS_DRAFT, Quiz::STATUS_IN_PROGRESS])
                    ->whereRaw('COALESCE(last_interacted_at, started_at, updated_at) <= ?', [$cutoff]);
            })
            ->chunkById(100, function ($students) use ($cooldownHours, $cutoff): void {
                foreach ($students as $student) {
                    $draftCount = $student->quizzes()
                        ->whereIn('status', [Quiz::STATUS_DRAFT, Quiz::STATUS_IN_PROGRESS])
                        ->whereRaw('COALESCE(last_interacted_at, started_at, updated_at) <= ?', [$cutoff])
                        ->count();

                    if ($draftCount < 1) {
                        continue;
                    }

                    $key = "student:{$student->id}:draft-quiz";
                    if (! $this->throttle->shouldSend($key, $cooldownHours)) {
                        continue;
                    }

                    $student->notify(new StudentDraftQuizReminderNotification($draftCount));
                }
            });
    }
}
