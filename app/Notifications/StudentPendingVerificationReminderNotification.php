<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudentPendingVerificationReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Billing verification is still pending')
            ->greeting('Hi '.$notifiable->name.',')
            ->line('Your payment/access verification is still pending.')
            ->line('Open billing to review your current status or upload a new payment proof if needed.')
            ->action('Open billing', route('student.billing.subscription'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'student_pending_verification_reminder',
            'message' => 'Your payment verification is still pending. Open billing to review status.',
            'action_url' => route('student.billing.subscription'),
        ];
    }
}
