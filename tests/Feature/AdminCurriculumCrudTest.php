<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCurriculumCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_admin_can_create_update_and_delete_subject_and_topic(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.subjects.store'), [
                'name' => 'Mathematics',
                'slug' => 'mathematics',
                'level' => \App\Models\Subject::LEVEL_O,
                'description' => 'Numbers and logic',
                'color' => '#3b82f6',
                'icon' => 'calculator',
                'is_active' => true,
                'sort_order' => 1,
            ])
            ->assertRedirect(route('admin.subjects.index'));

        $subject = Subject::query()->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.subjects.update', $subject), [
                'name' => 'Mathematics Updated',
                'slug' => 'mathematics-updated',
                'level' => \App\Models\Subject::LEVEL_A,
                'description' => 'Updated',
                'color' => '#0ea5e9',
                'icon' => 'plus',
                'is_active' => true,
                'sort_order' => 2,
            ])
            ->assertRedirect(route('admin.subjects.index'));

        $this->assertDatabaseHas('subjects', [
            'id' => $subject->id,
            'name' => 'Mathematics Updated',
            'slug' => 'mathematics-updated',
            'level' => \App\Models\Subject::LEVEL_A,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.topics.store'), [
                'subject_id' => $subject->id,
                'name' => 'Algebra',
                'slug' => 'algebra',
                'description' => 'Variables and equations',
                'is_active' => true,
                'sort_order' => 1,
            ])
            ->assertRedirect(route('admin.topics.index'));

        $topic = Topic::query()->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.topics.update', $topic), [
                'subject_id' => $subject->id,
                'name' => 'Algebra Basics',
                'slug' => 'algebra-basics',
                'description' => 'Updated topic',
                'is_active' => true,
                'sort_order' => 3,
            ])
            ->assertRedirect(route('admin.topics.index'));

        $this->assertDatabaseHas('topics', [
            'id' => $topic->id,
            'name' => 'Algebra Basics',
            'slug' => 'algebra-basics',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.topics.destroy', $topic))
            ->assertRedirect(route('admin.topics.index'));

        $this->assertSoftDeleted('topics', ['id' => $topic->id]);

        $this->actingAs($admin)
            ->delete(route('admin.subjects.destroy', $subject))
            ->assertRedirect(route('admin.subjects.index'));

        $this->assertSoftDeleted('subjects', ['id' => $subject->id]);
    }

    public function test_admin_can_create_update_toggle_publish_and_delete_question(): void
    {
        $admin = User::factory()->admin()->create();
        $subject = Subject::factory()->create();
        $topic = Topic::factory()->create(['subject_id' => $subject->id]);

        $storePayload = [
            'subject_id' => $subject->id,
            'topic_id' => $topic->id,
            'type' => Question::TYPE_MCQ,
            'question_text' => 'What is 10 + 5?',
            'explanation' => 'Add the values',
            'marks' => 2,
            'difficulty' => 'easy',
            'is_published' => true,
            'options' => [
                ['option_key' => 'A', 'option_text' => '15'],
                ['option_key' => 'B', 'option_text' => '20'],
            ],
            'correct_option_key' => 'A',
        ];

        $this->actingAs($admin)
            ->post(route('admin.questions.store'), $storePayload)
            ->assertRedirect();

        $question = Question::query()->firstOrFail();

        $this->assertDatabaseHas('questions', [
            'id' => $question->id,
            'type' => Question::TYPE_MCQ,
            'question_text' => 'What is 10 + 5?',
            'is_published' => true,
        ]);

        $this->assertDatabaseHas('mcq_options', [
            'question_id' => $question->id,
            'option_key' => 'A',
            'is_correct' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.questions.update', $question), [
                'subject_id' => $subject->id,
                'topic_id' => $topic->id,
                'type' => Question::TYPE_THEORY,
                'question_text' => 'Explain why practice matters.',
                'explanation' => 'Helps learning',
                'marks' => 3,
                'difficulty' => 'medium',
                'is_published' => false,
                'sample_answer' => 'Practice reinforces memory and understanding.',
                'grading_notes' => 'Look for reinforcement and confidence.',
                'keywords' => 'practice|memory|understanding',
                'acceptable_phrases' => 'build confidence|better retention',
            ])
            ->assertRedirect(route('admin.questions.index'));

        $this->assertDatabaseHas('questions', [
            'id' => $question->id,
            'type' => Question::TYPE_THEORY,
            'question_text' => 'Explain why practice matters.',
            'is_published' => false,
        ]);

        $this->assertDatabaseHas('theory_question_meta', [
            'question_id' => $question->id,
            'sample_answer' => 'Practice reinforces memory and understanding.',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.questions.toggle-publish', $question))
            ->assertRedirect(route('admin.questions.index'));

        $this->assertDatabaseHas('questions', [
            'id' => $question->id,
            'is_published' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.questions.destroy', $question))
            ->assertRedirect(route('admin.questions.index'));

        $this->assertSoftDeleted('questions', ['id' => $question->id]);
    }
}
