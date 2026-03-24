<?php

namespace App\Events;

use App\Models\Question;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuestionBankChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $action,
        public readonly ?int $questionId = null,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin.questions'),
            new PrivateChannel('admin.dashboard'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'question.bank.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'question_id' => $this->questionId,
            'changed_at' => now()->toIso8601String(),
            'question_total' => Question::query()->count(),
            'published_total' => Question::query()->where('is_published', true)->count(),
        ];
    }
}
