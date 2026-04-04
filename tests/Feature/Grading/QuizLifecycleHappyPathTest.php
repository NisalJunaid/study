<?php

namespace Tests\Feature\Grading;

use App\Models\McqOption;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizLifecycleHappyPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_create_and_submit_a_simple_mcq_quiz(): void
    {
        $this->withoutVite();

        $student = User::factory()->student()->create();

        $subject = Subject::factory()->create([
            'level' => Subject::LEVEL_O,
            'is_active' => true,
        ]);

        $question = Question::factory()->mcq()->for($subject)->create([
            'topic_id' => null,
            'difficulty' => 'easy',
            'marks' => 1,
            'is_published' => true,
        ]);

        McqOption::query()->create([
            'question_id' => $question->id,
            'option_key' => 'A',
            'option_text' => 'Correct option',
            'is_correct' => true,
            'sort_order' => 1,
        ]);

        $createResponse = $this->actingAs($student)->post(route('student.quiz.store'), [
            'levels' => [Subject::LEVEL_O],
            'multi_subject_mode' => false,
            'subject_id' => $subject->id,
            'mode' => Quiz::MODE_MCQ,
            'question_count' => 1,
            'difficulty' => 'easy',
        ]);

        $quiz = Quiz::query()->latest('id')->firstOrFail();

        $createResponse->assertRedirect(route('student.quiz.take', $quiz));
        $this->assertSame(Quiz::STATUS_IN_PROGRESS, $quiz->status);

        $submitResponse = $this->actingAs($student)->post(route('student.quiz.submit', $quiz));

        $submitResponse->assertRedirect(route('student.quiz.results', $quiz));
        $this->assertSame(Quiz::STATUS_GRADED, $quiz->fresh()->status);
    }
}
