<?php

namespace Tests\Feature;

use App\Models\McqOption;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\StructuredQuestionPart;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\TheoryQuestionMeta;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDataManagementAndBulkActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_admin_can_access_data_management_and_student_cannot(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();

        $this->actingAs($admin)->get(route('admin.data-management.index'))->assertOk();
        $this->actingAs($student)->get(route('admin.data-management.index'))->assertForbidden();

        $this->actingAs($student)
            ->post(route('admin.data-management.wipe'), [
                'scope' => 'subjects',
                'confirmation_text' => 'WIPE SUBJECTS',
            ])->assertForbidden();
    }

    public function test_wipe_questions_deletes_question_dependencies_safely(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $subject = Subject::factory()->create();
        $topic = Topic::factory()->create(['subject_id' => $subject->id]);

        $question = Question::factory()->create(['subject_id' => $subject->id, 'topic_id' => $topic->id, 'created_by' => $admin->id, 'updated_by' => $admin->id]);
        $option = McqOption::query()->create(['question_id' => $question->id, 'option_key' => 'A', 'option_text' => 'One', 'is_correct' => true, 'sort_order' => 1]);
        TheoryQuestionMeta::query()->create(['question_id' => $question->id, 'sample_answer' => 'Sample answer', 'max_score' => 1]);
        StructuredQuestionPart::query()->create(['question_id' => $question->id, 'part_label' => 'a', 'prompt_text' => 'Part A', 'max_score' => 1]);

        $quiz = Quiz::query()->create([
            'user_id' => $student->id,
            'subject_id' => $subject->id,
            'mode' => Quiz::MODE_MCQ,
            'status' => Quiz::STATUS_IN_PROGRESS,
        ]);

        $quizQuestion = QuizQuestion::query()->create([
            'quiz_id' => $quiz->id,
            'question_id' => $question->id,
            'order_no' => 1,
            'question_snapshot' => ['question_text' => 'snapshot'],
            'max_score' => 1,
        ]);

        StudentAnswer::query()->create([
            'quiz_question_id' => $quizQuestion->id,
            'question_id' => $question->id,
            'user_id' => $student->id,
            'selected_option_id' => $option->id,
            'grading_status' => StudentAnswer::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.data-management.wipe'), [
                'scope' => 'questions',
                'confirmation_text' => 'WIPE QUESTIONS',
            ])
            ->assertRedirect(route('admin.data-management.index'));

        $this->assertDatabaseEmpty('questions');
        $this->assertDatabaseEmpty('mcq_options');
        $this->assertDatabaseEmpty('theory_question_meta');
        $this->assertDatabaseEmpty('structured_question_parts');
        $this->assertDatabaseEmpty('quiz_questions');
        $this->assertDatabaseEmpty('student_answers');
    }

    public function test_wipe_subjects_topics_answers_and_all_routes_work(): void
    {
        $admin = User::factory()->admin()->create();

        $subject = Subject::factory()->create();
        $topic = Topic::factory()->create(['subject_id' => $subject->id]);
        $question = Question::factory()->create(['subject_id' => $subject->id, 'topic_id' => $topic->id]);
        McqOption::query()->create(['question_id' => $question->id, 'option_key' => 'A', 'option_text' => 'One', 'is_correct' => true, 'sort_order' => 1]);

        $this->actingAs($admin)
            ->post(route('admin.data-management.wipe'), [
                'scope' => 'answers',
                'confirmation_text' => 'WIPE ANSWERS',
            ])
            ->assertRedirect(route('admin.data-management.index'));

        $this->assertDatabaseEmpty('mcq_options');
        $this->assertDatabaseCount('questions', 1);

        $this->actingAs($admin)
            ->post(route('admin.data-management.wipe'), [
                'scope' => 'topics',
                'confirmation_text' => 'WIPE TOPICS',
            ])
            ->assertRedirect(route('admin.data-management.index'));

        $this->assertDatabaseEmpty('topics');
        $this->assertDatabaseEmpty('questions');

        $subject2 = Subject::factory()->create();
        $topic2 = Topic::factory()->create(['subject_id' => $subject2->id]);
        Question::factory()->create(['subject_id' => $subject2->id, 'topic_id' => $topic2->id]);

        $this->actingAs($admin)
            ->post(route('admin.data-management.wipe'), [
                'scope' => 'subjects',
                'confirmation_text' => 'WIPE SUBJECTS',
            ])
            ->assertRedirect(route('admin.data-management.index'));

        $this->assertDatabaseEmpty('subjects');

        Subject::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.data-management.wipe'), [
                'scope' => 'all',
                'confirmation_text' => 'WIPE ALL CONTENT',
            ])
            ->assertRedirect(route('admin.data-management.index'));

        $this->assertDatabaseEmpty('subjects');
        $this->assertDatabaseEmpty('topics');
        $this->assertDatabaseEmpty('questions');
    }

    public function test_wipe_requires_matching_confirmation_phrase(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.data-management.index'))
            ->post(route('admin.data-management.wipe'), [
                'scope' => 'all',
                'confirmation_text' => 'wrong',
            ])
            ->assertRedirect(route('admin.data-management.index'))
            ->assertSessionHasErrors('confirmation_text');
    }

    public function test_bulk_subject_topic_and_question_actions_work(): void
    {
        $admin = User::factory()->admin()->create();
        $subjectA = Subject::factory()->create(['level' => Subject::LEVEL_O, 'is_active' => true]);
        $subjectB = Subject::factory()->create(['level' => Subject::LEVEL_O, 'is_active' => true]);

        $topicA = Topic::factory()->create(['subject_id' => $subjectA->id, 'is_active' => true]);
        $topicB = Topic::factory()->create(['subject_id' => $subjectA->id, 'is_active' => true]);

        $questionA = Question::factory()->create(['subject_id' => $subjectA->id, 'topic_id' => $topicA->id, 'difficulty' => 'easy', 'is_published' => false]);
        $questionB = Question::factory()->create(['subject_id' => $subjectA->id, 'topic_id' => $topicA->id, 'difficulty' => 'easy', 'is_published' => false]);

        $this->actingAs($admin)
            ->post(route('admin.subjects.bulk-action'), [
                'ids' => [$subjectA->id, $subjectB->id],
                'action' => 'update',
                'update' => ['level' => Subject::LEVEL_A, 'is_active' => false],
            ])
            ->assertRedirect(route('admin.subjects.index'));

        $this->assertDatabaseHas('subjects', ['id' => $subjectA->id, 'level' => Subject::LEVEL_A, 'is_active' => false]);
        $this->assertDatabaseHas('subjects', ['id' => $subjectB->id, 'level' => Subject::LEVEL_A, 'is_active' => false]);

        $this->actingAs($admin)
            ->post(route('admin.topics.bulk-action'), [
                'ids' => [$topicA->id, $topicB->id],
                'action' => 'update',
                'update' => ['is_active' => false],
            ])
            ->assertRedirect(route('admin.topics.index'));

        $this->assertDatabaseHas('topics', ['id' => $topicA->id, 'is_active' => false]);
        $this->assertDatabaseHas('topics', ['id' => $topicB->id, 'is_active' => false]);

        $this->actingAs($admin)
            ->post(route('admin.questions.bulk-action'), [
                'ids' => [$questionA->id, $questionB->id],
                'action' => 'update',
                'update' => ['difficulty' => 'hard', 'is_published' => true, 'marks' => 4],
            ])
            ->assertRedirect(route('admin.questions.index'));

        $this->assertDatabaseHas('questions', ['id' => $questionA->id, 'difficulty' => 'hard', 'is_published' => true]);
        $this->assertDatabaseHas('questions', ['id' => $questionB->id, 'difficulty' => 'hard', 'is_published' => true]);

        $this->actingAs($admin)
            ->post(route('admin.subjects.bulk-action'), ['ids' => [$subjectA->id], 'action' => 'delete', 'delete_confirmation' => '1'])
            ->assertRedirect(route('admin.subjects.index'));
        $this->assertSoftDeleted('subjects', ['id' => $subjectA->id]);

        $this->actingAs($admin)
            ->post(route('admin.topics.bulk-action'), ['ids' => [$topicA->id], 'action' => 'delete', 'delete_confirmation' => '1'])
            ->assertRedirect(route('admin.topics.index'));
        $this->assertSoftDeleted('topics', ['id' => $topicA->id]);

        $this->actingAs($admin)
            ->post(route('admin.questions.bulk-action'), ['ids' => [$questionA->id], 'action' => 'delete', 'delete_confirmation' => '1'])
            ->assertRedirect(route('admin.questions.index'));
        $this->assertSoftDeleted('questions', ['id' => $questionA->id]);
    }

    public function test_bulk_delete_requires_explicit_confirmation_flag(): void
    {
        $admin = User::factory()->admin()->create();
        $subject = Subject::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.subjects.index'))
            ->post(route('admin.subjects.bulk-action'), [
                'ids' => [$subject->id],
                'action' => 'delete',
            ])
            ->assertRedirect(route('admin.subjects.index'))
            ->assertSessionHasErrors('delete_confirmation');

        $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'deleted_at' => null]);
    }

    public function test_non_admin_cannot_run_bulk_actions(): void
    {
        $student = User::factory()->student()->create();
        $subject = Subject::factory()->create();
        $topic = Topic::factory()->create(['subject_id' => $subject->id]);
        $question = Question::factory()->create(['subject_id' => $subject->id, 'topic_id' => $topic->id]);

        $this->actingAs($student)
            ->post(route('admin.subjects.bulk-action'), [
                'ids' => [$subject->id],
                'action' => 'delete',
                'delete_confirmation' => '1',
            ])->assertForbidden();

        $this->actingAs($student)
            ->post(route('admin.topics.bulk-action'), [
                'ids' => [$topic->id],
                'action' => 'delete',
                'delete_confirmation' => '1',
            ])->assertForbidden();

        $this->actingAs($student)
            ->post(route('admin.questions.bulk-action'), [
                'ids' => [$question->id],
                'action' => 'delete',
                'delete_confirmation' => '1',
            ])->assertForbidden();
    }
}
