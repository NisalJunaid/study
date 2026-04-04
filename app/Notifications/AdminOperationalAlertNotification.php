<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminOperationalAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param array<int,array<string,mixed>> $alerts */
    public function __construct(
        private readonly array $alerts,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Focus Lab operational alerts')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('The system detected operational items that need admin attention.');

        foreach ($this->alerts as $alert) {
            $mail->line('- '.($alert['title'] ?? 'Alert').': '.($alert['message'] ?? '')); 
        }

        return $mail->action('Open admin dashboard', route('admin.dashboard'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'admin_operational_alert',
            'alerts' => $this->alerts,
            'action_url' => route('admin.dashboard'),
        ];
    }
}
