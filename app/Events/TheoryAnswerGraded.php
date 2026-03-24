<?php

namespace App\Events;

use App\Models\StudentAnswer;
use App\Support\Broadcasting\RealtimePayload;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TheoryAnswerGraded implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $studentAnswerId,
    ) {
    }

    public function broadcastOn(): array
    {
        $answer = StudentAnswer::query()
            ->with('quizQuestion.quiz:id,user_id')
            ->find($this->studentAnswerId);

        if (! $answer || ! $answer->quizQuestion || ! $answer->quizQuestion->quiz) {
            return [];
        }

        $quiz = $answer->quizQuestion->quiz;

        return [
            new PrivateChannel("quiz.{$quiz->id}"),
            new PrivateChannel("user.{$quiz->user_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'theory.answer.graded';
    }

    public function broadcastWith(): array
    {
        $answer = StudentAnswer::query()->find($this->studentAnswerId);

        if (! $answer) {
            return [
                'answer' => ['id' => $this->studentAnswerId],
            ];
        }

        return [
            'answer' => RealtimePayload::theoryAnswer($answer),
        ];
    }
}
