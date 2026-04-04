<?php

namespace Tests\Feature;

use App\Models\McqOption;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminQuestionModerationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_invalid_mcq_question_cannot_be_published_via_toggle(): void
    {
        $admin = User::factory()->admin()->create();
        $subject = Subject::factory()->create();

        $question = Question::factory()->create([
            'subject_id' => $subject->id,
            'topic_id' => null,
            'type' => Question::TYPE_MCQ,
            'is_published' => false,
        ]);

        McqOption::query()->create([
            'question_id' => $question->id,
            'option_key' => 'A',
            'option_text' => 'Only option',
            'is_correct' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.questions.toggle-publish', $question))
            ->assertRedirect(route('admin.questions.edit', $question));

        $this->assertDatabaseHas('questions', [
            'id' => $question->id,
            'is_published' => false,
        ]);
    }

    public function test_flagged_questions_appear_in_filter_results(): void
    {
        $admin = User::factory()->admin()->create();
        $subject = Subject::factory()->create();
        $topic = Topic::factory()->create(['subject_id' => $subject->id]);

        $this->actingAs($admin)
            ->post(route('admin.questions.store'), [
                'subject_id' => $subject->id,
                'topic_id' => $topic->id,
                'type' => Question::TYPE_MCQ,
                'question_text' => 'Which is correct and flagged?',
                'explanation' => '',
                'marks' => 2,
                'difficulty' => 'easy',
                'is_published' => 0,
                'options' => [
                    ['option_key' => 'A', 'option_text' => 'Option 1'],
                    ['option_key' => 'B', 'option_text' => 'Option 2'],
                ],
                'correct_option_key' => 'A',
            ])
            ->assertRedirect();

        Question::factory()->create([
            'subject_id' => $subject->id,
            'topic_id' => $topic->id,
            'type' => Question::TYPE_THEORY,
            'question_text' => 'This one is not flagged.',
            'explanation' => 'Has explanation',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.questions.index', ['flag' => Question::FLAG_MISSING_EXPLANATION]));

        $response->assertOk();
        $response->assertSee('Which is correct and flagged?');
        $response->assertDontSee('This one is not flagged.');
    }

    public function test_admin_can_dismiss_duplicate_flag(): void
    {
        $admin = User::factory()->admin()->create();
        $question = Question::factory()->create([
            'moderation_flags' => [Question::FLAG_DUPLICATE_SUSPECTED, Question::FLAG_MISSING_EXPLANATION],
        ]);

        $this->actingAs($admin)
            ->post(route('admin.questions.dismiss-flag', $question), [
                'flag' => Question::FLAG_DUPLICATE_SUSPECTED,
            ])
            ->assertRedirect();

        $question->refresh();

        $this->assertFalse($question->hasModerationFlag(Question::FLAG_DUPLICATE_SUSPECTED));
        $this->assertTrue($question->hasModerationFlag(Question::FLAG_MISSING_EXPLANATION));
    }

    public function test_valid_question_publish_flow_still_works(): void
    {
        $admin = User::factory()->admin()->create();
        $subject = Subject::factory()->create();

        $question = Question::factory()->create([
            'subject_id' => $subject->id,
            'topic_id' => null,
            'type' => Question::TYPE_THEORY,
            'marks' => 3,
            'is_published' => false,
        ]);

        $question->theoryMeta()->create([
            'sample_answer' => 'A full sample answer.',
            'grading_notes' => 'Assess clarity.',
            'keywords' => ['full'],
            'acceptable_phrases' => ['sample'],
            'max_score' => 3,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.questions.toggle-publish', $question))
            ->assertRedirect(route('admin.questions.index'));

        $this->assertDatabaseHas('questions', [
            'id' => $question->id,
            'is_published' => true,
        ]);
    }
}
