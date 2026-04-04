<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudentDraftQuizReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $draftCount,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You have unfinished quizzes')
            ->greeting('Hi '.$notifiable->name.',')
            ->line("You currently have {$this->draftCount} unfinished draft quiz(es).")
            ->line('Resume and submit them to get your latest feedback.')
            ->action('Resume quiz', route('student.history.index'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'student_draft_quiz_reminder',
            'draft_count' => $this->draftCount,
            'message' => 'You have unfinished draft quizzes ready to resume.',
            'action_url' => route('student.history.index'),
        ];
    }
}
