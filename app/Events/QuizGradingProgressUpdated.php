<?php

namespace App\Events;

use App\Models\Quiz;
use App\Support\Broadcasting\RealtimePayload;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuizGradingProgressUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $quizId,
    ) {
    }

    public function broadcastOn(): array
    {
        $quiz = Quiz::query()->find($this->quizId);

        if (! $quiz) {
            return [];
        }

        return [
            new PrivateChannel("quiz.{$quiz->id}"),
            new PrivateChannel("user.{$quiz->user_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'quiz.grading.progress.updated';
    }

    public function broadcastWith(): array
    {
        $quiz = Quiz::query()->find($this->quizId);

        if (! $quiz) {
            return [
                'quiz' => ['id' => $this->quizId],
            ];
        }

        return [
            'quiz' => RealtimePayload::quizProgress($quiz),
        ];
    }
}
