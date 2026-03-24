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
            ->assertSee('View Results')
            ->assertDontSee('8.00 / 9.00');
    }

    public function test_progress_dashboard_displays_summary_and_weak_topics_from_student_data_only(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $otherStudent = User::factory()->create(['role' => User::ROLE_STUDENT]);

        $subject = Subject::factory()->create(['name' => 'Mathematics']);
        $topicWeak = Topic::factory()->create(['subject_id' => $subject->id, 'name' => 'Algebra']);
        $topicStrong = Topic::factory()->create(['subject_id' => $subject->id, 'name' => 'Geometry']);

        $weakQuestion = Question::factory()->create(['subject_id' => $subject->id, 'topic_id' => $topicWeak->id, 'type' => Question::TYPE_THEORY]);
        $strongQuestion = Question::factory()->create(['subject_id' => $subject->id, 'topic_id' => $topicStrong->id, 'type' => Question::TYPE_THEORY]);

        $quizOne = Quiz::query()->create([
            'user_id' => $student->id,
            'subject_id' => $subject->id,
            'status' => Quiz::STATUS_GRADED,
            'mode' => Quiz::MODE_THEORY,
            'total_questions' => 2,
            'total_possible_score' => 10,
            'total_awarded_score' => 6,
            'submitted_at' => now()->subDays(2),
        ]);

        $quizTwo = Quiz::query()->create([
            'user_id' => $student->id,
            'subject_id' => $subject->id,
            'status' => Quiz::STATUS_GRADED,
            'mode' => Quiz::MODE_THEORY,
            'total_questions' => 2,
            'total_possible_score' => 10,
            'total_awarded_score' => 8,
            'submitted_at' => now()->subDay(),
        ]);

        $this->createAnsweredQuizQuestion($quizOne, $weakQuestion, 1, 1.0);
        $this->createAnsweredQuizQuestion($quizTwo, $weakQuestion, 2, 1.5);
        $this->createAnsweredQuizQuestion($quizTwo, $strongQuestion, 3, 3.0);

        $foreignQuiz = Quiz::query()->create([
            'user_id' => $otherStudent->id,
            'subject_id' => $subject->id,
            'status' => Quiz::STATUS_GRADED,
            'mode' => Quiz::MODE_THEORY,
            'total_questions' => 1,
            'total_possible_score' => 4,
            'total_awarded_score' => 4,
            'submitted_at' => now(),
        ]);
        $this->createAnsweredQuizQuestion($foreignQuiz, $weakQuestion, 1, 4.0, $otherStudent->id);

        $this->actingAs($student)
            ->get(route('student.progress.index'))
            ->assertOk()
            ->assertSee('Completed quizzes')
            ->assertSee('2')
            ->assertSee('Algebra')
            ->assertDontSee('100.0%');
    }

    private function createAnsweredQuizQuestion(Quiz $quiz, Question $question, int $orderNo, float $score, ?int $userId = null): void
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
            'grading_status' => StudentAnswer::STATUS_GRADED,
        ]);
    }
}
