<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentQuizPresetFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_quick_revision_preset_prefills_existing_setup_fields(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Subject::factory()->create(['is_active' => true]);

        $this->actingAs($student)
            ->get(route('student.quiz.setup', ['preset' => 'quick_revision']))
            ->assertOk()
            ->assertSee('name="question_count" value="10"', false)
            ->assertSee('<option value="mixed" selected>', false)
            ->assertSee('name="preset" value="quick_revision"', false);
    }

    public function test_weak_topics_preset_uses_real_student_topic_performance_data(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $subject = Subject::factory()->create(['is_active' => true]);
        $weakTopic = Topic::factory()->create(['subject_id' => $subject->id, 'is_active' => true, 'name' => 'Weak Algebra']);
        $strongTopic = Topic::factory()->create(['subject_id' => $subject->id, 'is_active' => true, 'name' => 'Strong Geometry']);

        $weakQuestion = Question::factory()->create(['subject_id' => $subject->id, 'topic_id' => $weakTopic->id, 'type' => Question::TYPE_THEORY, 'is_published' => true]);
        $strongQuestion = Question::factory()->create(['subject_id' => $subject->id, 'topic_id' => $strongTopic->id, 'type' => Question::TYPE_THEORY, 'is_published' => true]);

        $quizOne = $this->createQuiz($student->id, $subject->id, now()->subDays(3));
        $quizTwo = $this->createQuiz($student->id, $subject->id, now()->subDays(2));

        $this->createAnsweredQuizQuestion($quizOne, $weakQuestion, 1, 0.5);
        $this->createAnsweredQuizQuestion($quizTwo, $weakQuestion, 2, 1.0);
        $this->createAnsweredQuizQuestion($quizTwo, $strongQuestion, 3, 4.0);

        $response = $this->actingAs($student)
            ->get(route('student.quiz.setup', ['preset' => 'weak_topics_only']))
            ->assertOk();

        $content = $response->getContent();

        $this->assertMatchesRegularExpression('/name="subject_id" value="'.$subject->id.'" class="subject-single-input" checked/', $content);
        $this->assertMatchesRegularExpression('/name="topic_ids\[\]" value="'.$weakTopic->id.'" checked/', $content);
    }

    public function test_weak_topics_preset_falls_back_when_student_has_no_topic_history(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Subject::factory()->create(['is_active' => true]);

        $this->actingAs($student)
            ->get(route('student.quiz.setup', ['preset' => 'weak_topics_only']))
            ->assertOk()
            ->assertSee('Weak-topics preset needs previous graded topic data. We switched you to a mixed practice setup.')
            ->assertSee('name="question_count" value="20"', false)
            ->assertSee('<option value="mixed" selected>', false);
    }

    public function test_manual_quiz_setup_submission_still_uses_existing_pipeline(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $subject = Subject::factory()->create(['is_active' => true]);

        Question::factory()->count(4)->create([
            'subject_id' => $subject->id,
            'topic_id' => null,
            'type' => Question::TYPE_MCQ,
            'is_published' => true,
        ]);

        $response = $this->actingAs($student)
            ->post(route('student.quiz.store'), [
                'guided_step' => 5,
                'levels' => [$subject->level],
                'multi_subject_mode' => false,
                'subject_id' => $subject->id,
                'mode' => Quiz::MODE_MCQ,
                'question_count' => 3,
            ]);

        $quiz = Quiz::query()->latest('id')->firstOrFail();

        $response->assertRedirect(route('student.quiz.take', $quiz));

        $this->assertDatabaseHas('quizzes', [
            'id' => $quiz->id,
            'user_id' => $student->id,
            'mode' => Quiz::MODE_MCQ,
            'total_questions' => 3,
        ]);
    }

    private function createQuiz(int $userId, int $subjectId, $submittedAt): Quiz
    {
        return Quiz::query()->create([
            'user_id' => $userId,
            'subject_id' => $subjectId,
            'status' => Quiz::STATUS_GRADED,
            'mode' => Quiz::MODE_THEORY,
            'total_questions' => 2,
            'total_possible_score' => 10,
            'total_awarded_score' => 4,
            'submitted_at' => $submittedAt,
        ]);
    }

    private function createAnsweredQuizQuestion(Quiz $quiz, Question $question, int $orderNo, float $score): void
    {
        $quizQuestion = QuizQuestion::query()->create([
            'quiz_id' => $quiz->id,
            'question_id' => $question->id,
            'order_no' => $orderNo,
            'question_snapshot' => [
                'type' => $question->type,
                'question_text' => 'Snapshot',
            ],
            'max_score' => 4,
            'awarded_score' => $score,
        ]);

        StudentAnswer::query()->create([
            'quiz_question_id' => $quizQuestion->id,
            'question_id' => $question->id,
            'user_id' => $quiz->user_id,
            'answer_text' => 'Sample answer',
            'score' => $score,
            'grading_status' => StudentAnswer::STATUS_GRADED,
        ]);
    }
}
