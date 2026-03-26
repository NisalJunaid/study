<?php

namespace Tests\Feature;

use App\Actions\Student\BuildQuizAction;
use App\Models\McqOption;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\TheoryQuestionMeta;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentQuizTakingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }


    public function test_quiz_builder_uses_guided_multi_step_copy(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $subject = Subject::factory()->create(['is_active' => true]);

        $this->actingAs($student)
            ->get(route('student.quiz.setup'))
            ->assertOk()
            ->assertSee('Quiz setup progress')
            ->assertSee('Step 5: Review and start')
            ->assertSee('Step 1: Select level(s)');
    }

    public function test_student_can_autosave_answer_and_submit_quiz_with_mcq_and_theory(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $subject = Subject::factory()->create(['is_active' => true]);
        $topic = Topic::factory()->create(['subject_id' => $subject->id, 'is_active' => true]);

        $mcqQuestion = Question::query()->create([
            'subject_id' => $subject->id,
            'topic_id' => $topic->id,
            'type' => Question::TYPE_MCQ,
            'question_text' => 'What is 2 + 2?',
            'difficulty' => 'easy',
            'marks' => 2,
            'is_published' => true,
        ]);

        $mcqOptionA = McqOption::query()->create([
            'question_id' => $mcqQuestion->id,
            'option_key' => 'A',
            'option_text' => '3',
            'is_correct' => false,
            'sort_order' => 1,
        ]);

        $mcqOptionB = McqOption::query()->create([
            'question_id' => $mcqQuestion->id,
            'option_key' => 'B',
            'option_text' => '4',
            'is_correct' => true,
            'sort_order' => 2,
        ]);

        $theoryQuestion = Question::query()->create([
            'subject_id' => $subject->id,
            'topic_id' => $topic->id,
            'type' => Question::TYPE_THEORY,
            'question_text' => 'Explain photosynthesis.',
            'difficulty' => 'medium',
            'marks' => 3,
            'is_published' => true,
        ]);

        TheoryQuestionMeta::query()->create([
            'question_id' => $theoryQuestion->id,
            'sample_answer' => 'Plants use sunlight to convert carbon dioxide and water into glucose.',
            'max_score' => 3,
        ]);

        $quiz = app(BuildQuizAction::class)->execute($student, [
            'subject_id' => $subject->id,
            'topic_ids' => [$topic->id],
            'mode' => Quiz::MODE_MIXED,
            'question_count' => 2,
            'difficulty' => null,
        ]);

        $this->actingAs($student);

        $mcqQuizQuestion = $quiz->quizQuestions()->where('question_id', $mcqQuestion->id)->firstOrFail();
        $theoryQuizQuestion = $quiz->quizQuestions()->where('question_id', $theoryQuestion->id)->firstOrFail();

        $this->putJson(route('student.quiz.answer.save', [$quiz, $mcqQuizQuestion]), [
            'selected_option_id' => $mcqOptionB->id,
        ])->assertOk();

        $this->putJson(route('student.quiz.answer.save', [$quiz, $theoryQuizQuestion]), [
            'answer_text' => 'It is the process where plants make food.',
        ])->assertOk();

        $this->assertDatabaseHas('student_answers', [
            'quiz_question_id' => $mcqQuizQuestion->id,
            'selected_option_id' => $mcqOptionB->id,
        ]);

        $this->post(route('student.quiz.submit', $quiz))
            ->assertRedirect(route('student.quiz.results', $quiz))
            ->assertSessionMissing('overlay');

        $quiz->refresh();

        $this->assertContains($quiz->status, [Quiz::STATUS_GRADING, Quiz::STATUS_GRADED]);
        $this->assertNotNull($quiz->submitted_at);
        $this->assertSame('2.00', $quiz->total_awarded_score);

        $this->assertDatabaseHas('student_answers', [
            'quiz_question_id' => $mcqQuizQuestion->id,
            'is_correct' => true,
            'grading_status' => StudentAnswer::STATUS_GRADED,
        ]);

        $this->assertDatabaseHas('student_answers', [
            'quiz_question_id' => $theoryQuizQuestion->id,
            'answer_text' => 'It is the process where plants make food.',
        ]);

        $theoryAnswer = StudentAnswer::query()->where('quiz_question_id', $theoryQuizQuestion->id)->firstOrFail();
        $this->assertContains($theoryAnswer->grading_status, [
            StudentAnswer::STATUS_PENDING,
            StudentAnswer::STATUS_PROCESSING,
            StudentAnswer::STATUS_GRADED,
            StudentAnswer::STATUS_MANUAL_REVIEW,
        ]);

        $this->assertDatabaseMissing('student_answers', [
            'quiz_question_id' => $mcqQuizQuestion->id,
            'selected_option_id' => $mcqOptionA->id,
        ]);
    }

    public function test_student_cannot_access_or_save_other_students_quiz(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $otherStudent = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $subject = Subject::factory()->create();
        $question = Question::factory()->create(['subject_id' => $subject->id, 'topic_id' => null, 'is_published' => true]);

        $quiz = Quiz::query()->create([
            'user_id' => $owner->id,
            'subject_id' => $subject->id,
            'mode' => Quiz::MODE_THEORY,
            'status' => Quiz::STATUS_IN_PROGRESS,
            'total_questions' => 1,
            'total_possible_score' => 1,
            'started_at' => now(),
        ]);

        $quizQuestion = $quiz->quizQuestions()->create([
            'question_id' => $question->id,
            'order_no' => 1,
            'question_snapshot' => [
                'type' => 'theory',
                'question_text' => 'Restricted question',
            ],
            'max_score' => 1,
        ]);

        $this->actingAs($otherStudent);

        $this->get(route('student.quiz.take', $quiz))->assertForbidden();

        $this->putJson(route('student.quiz.answer.save', [$quiz, $quizQuestion]), [
            'answer_text' => 'Invalid access',
        ])->assertForbidden();
    }

    public function test_quiz_snapshot_is_used_even_if_original_question_changes(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $subject = Subject::factory()->create(['is_active' => true]);
        $topic = Topic::factory()->create(['subject_id' => $subject->id, 'is_active' => true]);

        $question = Question::query()->create([
            'subject_id' => $subject->id,
            'topic_id' => $topic->id,
            'type' => Question::TYPE_MCQ,
            'question_text' => 'Original snapshot text?',
            'marks' => 1,
            'is_published' => true,
        ]);

        McqOption::query()->create([
            'question_id' => $question->id,
            'option_key' => 'A',
            'option_text' => 'First option',
            'is_correct' => true,
            'sort_order' => 1,
        ]);

        $quiz = app(BuildQuizAction::class)->execute($student, [
            'subject_id' => $subject->id,
            'topic_ids' => [$topic->id],
            'mode' => Quiz::MODE_MCQ,
            'question_count' => 1,
            'difficulty' => null,
        ]);

        $question->update([
            'question_text' => 'Changed after assignment?',
            'is_published' => false,
        ]);

        $this->actingAs($student)
            ->get(route('student.quiz.take', $quiz))
            ->assertOk()
            ->assertSee('Original snapshot text?')
            ->assertDontSee('Changed after assignment?');
    }
}
