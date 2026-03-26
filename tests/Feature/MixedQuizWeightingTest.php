<?php

namespace Tests\Feature;

use App\Actions\Student\BuildQuizAction;
use App\Models\PaymentSetting;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Subject;
use App\Models\TheoryQuestionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MixedQuizWeightingTest extends TestCase
{
    use RefreshDatabase;

    public function test_mixed_quiz_generation_uses_admin_configured_ai_weight_percentage(): void
    {
        $student = User::factory()->student()->create();
        $subject = Subject::factory()->create(['is_active' => true]);

        PaymentSetting::current()->update(['mixed_quiz_ai_weight_percentage' => 25]);

        Question::factory()->count(12)->create([
            'subject_id' => $subject->id,
            'type' => Question::TYPE_MCQ,
            'is_published' => true,
            'topic_id' => null,
        ]);

        $theoryQuestions = Question::factory()->count(12)->create([
            'subject_id' => $subject->id,
            'type' => Question::TYPE_THEORY,
            'is_published' => true,
            'topic_id' => null,
        ]);

        foreach ($theoryQuestions as $question) {
            TheoryQuestionMeta::query()->create([
                'question_id' => $question->id,
                'sample_answer' => 'Reference answer',
                'max_score' => 1,
            ]);
        }

        $quiz = app(BuildQuizAction::class)->execute($student, [
            'levels' => [$subject->level],
            'subject_ids' => [$subject->id],
            'mode' => Quiz::MODE_MIXED,
            'question_count' => 8,
        ]);

        $types = $quiz->quizQuestions->pluck('question_snapshot.type');

        $this->assertSame(2, $types->filter(fn ($type) => in_array($type, Question::theoryLikeTypes(), true))->count());
        $this->assertSame(6, $types->filter(fn ($type) => $type === Question::TYPE_MCQ)->count());
    }
}

