<?php

namespace Tests\Unit;

use App\Models\Quiz;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class QuizLifecycleTransitionRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_quiz_lifecycle_transitions_are_allowed(): void
    {
        $quiz = $this->makeQuizWithStatus(Quiz::STATUS_IN_PROGRESS);

        $quiz->transitionTo(Quiz::STATUS_SUBMITTED, [
            'submitted_at' => now(),
        ]);
        $quiz->transitionTo(Quiz::STATUS_GRADING);
        $quiz->transitionTo(Quiz::STATUS_GRADED, [
            'graded_at' => now(),
        ]);

        $this->assertSame(Quiz::STATUS_GRADED, $quiz->fresh()->status);
    }

    public function test_invalid_quiz_lifecycle_transition_is_blocked(): void
    {
        $quiz = $this->makeQuizWithStatus(Quiz::STATUS_GRADED);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid quiz state transition');

        $quiz->transitionTo(Quiz::STATUS_IN_PROGRESS);
    }

    private function makeQuizWithStatus(string $status): Quiz
    {
        $student = User::factory()->student()->create();
        $subject = Subject::factory()->create();

        return Quiz::query()->create([
            'user_id' => $student->id,
            'subject_id' => $subject->id,
            'mode' => Quiz::MODE_THEORY,
            'status' => $status,
            'total_questions' => 1,
            'total_possible_score' => 1,
            'started_at' => now(),
            'submitted_at' => in_array($status, Quiz::submittedAttemptStatuses(), true) ? now() : null,
            'graded_at' => $status === Quiz::STATUS_GRADED ? now() : null,
        ]);
    }
}
