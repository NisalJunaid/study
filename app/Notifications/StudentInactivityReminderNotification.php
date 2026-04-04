<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudentInactivityReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $inactiveDays,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('We miss you in Focus Lab')
            ->greeting('Hi '.$notifiable->name.',')
            ->line("You have been inactive for {$this->inactiveDays} days.")
            ->line('A short quiz today will keep your momentum going.')
            ->action('Continue studying', route('student.quiz.builder'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'student_inactivity_reminder',
            'inactive_days' => $this->inactiveDays,
            'message' => 'You have been inactive. Continue your study streak with a quick quiz.',
            'action_url' => route('student.quiz.builder'),
        ];
    }
}
