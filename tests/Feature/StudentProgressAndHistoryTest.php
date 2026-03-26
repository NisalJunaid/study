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

class StudentProgressAndHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_history_lists_only_authenticated_students_own_quizzes(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $otherStudent = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $subject = Subject::factory()->create();

        Quiz::query()->create([
            'user_id' => $student->id,
            'subject_id' => $subject->id,
            'mode' => Quiz::MODE_MCQ,
            'status' => Quiz::STATUS_GRADED,
            'total_questions' => 5,
            'total_possible_score' => 5,
            'total_awarded_score' => 4,
            'submitted_at' => now()->subDays(2),
        ]);

        Quiz::query()->create([
            'user_id' => $student->id,
            'subject_id' => $subject->id,
            'mode' => Quiz::MODE_MCQ,
            'status' => Quiz::STATUS_GRADING,
            'total_questions' => 6,
            'total_possible_score' => 6,
            'total_awarded_score' => 5,
            'submitted_at' => now()->subDay(),
        ]);

        Quiz::query()->create([
            'user_id' => $otherStudent->id,
            'subject_id' => $subject->id,
            'mode' => Quiz::MODE_MCQ,
            'status' => Quiz::STATUS_GRADED,
            'total_questions' => 9,
            'total_possible_score' => 9,
            'total_awarded_score' => 8,
            'submitted_at' => now(),
        ]);

        $this->actingAs($student)
            ->get(route('student.history.index'))
            ->assertOk()
            ->assertSee((string) $subject->name)
            ->assertSee('View')
            ->assertDontSee('8.00 / 9.00');
    }

    public function test_progress_dashboard_displays_new_summary_weak_areas_and_activity_drawer_data(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $otherStudent = User::factory()->create(['role' => User::ROLE_STUDENT]);

        $math = Subject::factory()->create(['name' => 'Mathematics']);
        $english = Subject::factory()->create(['name' => 'English']);

        $algebra = Topic::factory()->create(['subject_id' => $math->id, 'name' => 'Algebra']);
        $geometry = Topic::factory()->create(['subject_id' => $math->id, 'name' => 'Geometry']);
        $grammar = Topic::factory()->create(['subject_id' => $english->id, 'name' => 'Grammar']);

        $algebraQuestion = Question::factory()->create(['subject_id' => $math->id, 'topic_id' => $algebra->id, 'type' => Question::TYPE_THEORY]);
        $geometryQuestion = Question::factory()->create(['subject_id' => $math->id, 'topic_id' => $geometry->id, 'type' => Question::TYPE_THEORY]);
        $grammarQuestion = Question::factory()->create(['subject_id' => $english->id, 'topic_id' => $grammar->id, 'type' => Question::TYPE_THEORY]);

        $quizOne = $this->createQuiz($student->id, $math->id, 6, 10, now()->subDays(4));
        $quizTwo = $this->createQuiz($student->id, $math->id, 5, 10, now()->subDays(3));
        $quizThree = $this->createQuiz($student->id, $english->id, 9, 10, now()->subDays(2));
        $quizFour = $this->createQuiz($student->id, $english->id, 8, 10, now()->subDay());

        $this->createAnsweredQuizQuestion($quizOne, $algebraQuestion, 1, 1.0, null, false);
        $this->createAnsweredQuizQuestion($quizTwo, $algebraQuestion, 2, 1.5, null, false);
        $this->createAnsweredQuizQuestion($quizTwo, $geometryQuestion, 3, 3.0, null, true);
        $this->createAnsweredQuizQuestion($quizThree, $grammarQuestion, 1, 3.6, null, true);
        $this->createAnsweredQuizQuestion($quizFour, $grammarQuestion, 2, 3.8, null, true);

        $foreignQuiz = $this->createQuiz($otherStudent->id, $math->id, 10, 10, now());
        $this->createAnsweredQuizQuestion($foreignQuiz, $algebraQuestion, 1, 4.0, $otherStudent->id, true);

        $response = $this->actingAs($student)
            ->get(route('student.progress.index'))
            ->assertOk()
            ->assertSee('Average accuracy')
            ->assertSee('On-time answer rate')
            ->assertSee('Weak areas to focus on')
            ->assertSee('View All')
            ->assertSee('All recent activity')
            ->assertSee('Algebra')
            ->assertDontSee('10.00 / 10.00')
            ->assertSee('data-progress-chart="scoreTrend"', false)
            ->assertSee('data-activity-drawer-open', false);

        preg_match_all('/class="card-soft progress-activity-item"/', $response->getContent(), $matches);
        $this->assertNotEmpty($matches);
    }

    private function createQuiz(int $userId, int $subjectId, float $awarded, float $possible, $submittedAt): Quiz
    {
        return Quiz::query()->create([
            'user_id' => $userId,
            'subject_id' => $subjectId,
            'status' => Quiz::STATUS_GRADED,
            'mode' => Quiz::MODE_THEORY,
            'total_questions' => 2,
            'total_possible_score' => $possible,
            'total_awarded_score' => $awarded,
            'submitted_at' => $submittedAt,
        ]);
    }

    private function createAnsweredQuizQuestion(Quiz $quiz, Question $question, int $orderNo, float $score, ?int $userId = null, ?bool $answeredOnTime = null): void
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
            'user_id' => $userId ?? $quiz->user_id,
            'answer_text' => 'Sample answer',
            'score' => $score,
            'answered_on_time' => $answeredOnTime,
            'grading_status' => StudentAnswer::STATUS_GRADED,
        ]);
    }
}
