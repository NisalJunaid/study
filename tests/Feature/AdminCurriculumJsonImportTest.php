<?php

namespace Tests\Feature;

use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminCurriculumJsonImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_admin_can_import_subjects_json_and_upsert_by_slug(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        Subject::factory()->create([
            'name' => 'Chemistry Old',
            'slug' => 'chemistry',
            'level' => Subject::LEVEL_O,
            'color' => '#111111',
        ]);

        $payload = json_encode([
            [
                'name' => 'Chemistry',
                'slug' => 'chemistry',
                'level' => Subject::LEVEL_A,
                'color' => '#22c55e',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Biology',
                'slug' => 'biology',
                'level' => Subject::LEVEL_O,
                'color' => '#16a34a',
                'is_active' => true,
                'sort_order' => 2,
            ],
        ], JSON_THROW_ON_ERROR);

        $file = UploadedFile::fake()->createWithContent('subjects.json', $payload);

        $response = $this->actingAs($admin)->post(route('admin.imports.subjects.store'), [
            'subject_import_file' => $file,
        ]);

        $response->assertRedirect(route('admin.imports.index'));

        $this->assertDatabaseHas('subjects', [
            'slug' => 'chemistry',
            'name' => 'Chemistry',
            'level' => Subject::LEVEL_A,
            'color' => '#22c55e',
            'sort_order' => 4,
        ]);

        $this->assertDatabaseHas('subjects', [
            'slug' => 'biology',
            'name' => 'Biology',
            'level' => Subject::LEVEL_O,
        ]);
    }

    public function test_admin_can_import_topics_json_and_upsert_by_subject_slug_and_topic_slug(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $subject = Subject::factory()->create([
            'name' => 'Biology',
            'slug' => 'biology',
        ]);

        Topic::factory()->create([
            'subject_id' => $subject->id,
            'name' => 'Cell Basics',
            'slug' => 'cell-structure',
            'sort_order' => 1,
        ]);

        $payload = json_encode([
            [
                'subject_slug' => 'biology',
                'name' => 'Cell Structure',
                'slug' => 'cell-structure',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'subject_slug' => 'biology',
                'name' => 'Genetics',
                'slug' => 'genetics',
                'is_active' => true,
                'sort_order' => 6,
            ],
        ], JSON_THROW_ON_ERROR);

        $file = UploadedFile::fake()->createWithContent('topics.json', $payload);

        $response = $this->actingAs($admin)->post(route('admin.imports.topics.store'), [
            'topic_import_file' => $file,
        ]);

        $response->assertRedirect(route('admin.imports.index'));

        $this->assertDatabaseHas('topics', [
            'subject_id' => $subject->id,
            'slug' => 'cell-structure',
            'name' => 'Cell Structure',
            'sort_order' => 5,
        ]);

        $this->assertDatabaseHas('topics', [
            'subject_id' => $subject->id,
            'slug' => 'genetics',
            'name' => 'Genetics',
            'sort_order' => 6,
        ]);
    }

    public function test_subject_import_rejects_malformed_json(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $file = UploadedFile::fake()->createWithContent('subjects.json', '[{"name": "Biology"}');

        $this->actingAs($admin)
            ->post(route('admin.imports.subjects.store'), [
                'subject_import_file' => $file,
            ])
            ->assertSessionHasErrors('subject_import_file');
    }

    public function test_subject_import_rejects_invalid_level(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $file = UploadedFile::fake()->createWithContent('subjects.json', json_encode([
            [
                'name' => 'Biology',
                'slug' => 'biology',
                'level' => 'college',
            ],
        ], JSON_THROW_ON_ERROR));

        $this->actingAs($admin)
            ->post(route('admin.imports.subjects.store'), [
                'subject_import_file' => $file,
            ])
            ->assertSessionHasErrors('subjects.1');
    }

    public function test_topic_import_rejects_missing_subject_reference(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $file = UploadedFile::fake()->createWithContent('topics.json', json_encode([
            [
                'subject_slug' => 'unknown-subject',
                'name' => 'Cell Structure',
                'slug' => 'cell-structure',
            ],
        ], JSON_THROW_ON_ERROR));

        $this->actingAs($admin)
            ->post(route('admin.imports.topics.store'), [
                'topic_import_file' => $file,
            ])
            ->assertSessionHasErrors('topics.1');
    }

    public function test_topic_import_rejects_duplicate_topic_slug_for_same_subject_in_payload(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        Subject::factory()->create([
            'name' => 'Biology',
            'slug' => 'biology',
        ]);

        $file = UploadedFile::fake()->createWithContent('topics.json', json_encode([
            [
                'subject_slug' => 'biology',
                'name' => 'Cell Structure 1',
                'slug' => 'cell-structure',
            ],
            [
                'subject_slug' => 'biology',
                'name' => 'Cell Structure 2',
                'slug' => 'cell-structure',
            ],
        ], JSON_THROW_ON_ERROR));

        $this->actingAs($admin)
            ->post(route('admin.imports.topics.store'), [
                'topic_import_file' => $file,
            ])
            ->assertSessionHasErrors('topics.2');
    }

    public function test_admin_can_download_subject_and_topic_json_samples(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get(route('admin.imports.subjects.sample'))
            ->assertOk()
            ->assertHeader('content-type', 'application/json')
            ->assertSee('"level"', false)
            ->assertSee('"slug"', false);

        $this->actingAs($admin)
            ->get(route('admin.imports.topics.sample'))
            ->assertOk()
            ->assertHeader('content-type', 'application/json')
            ->assertSee('"subject_slug"', false)
            ->assertSee('"slug"', false);
    }

    public function test_admin_imports_page_renders_with_subject_and_topic_sample_links(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get(route('admin.imports.index'))
            ->assertOk()
            ->assertSee(route('admin.imports.subjects.sample'), false)
            ->assertSee(route('admin.imports.topics.sample'), false)
            ->assertSee(route('admin.imports.sample', ['template' => 'general']), false);
    }

    public function test_manual_subject_and_topic_crud_endpoints_continue_to_work(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $subjectResponse = $this->actingAs($admin)->post(route('admin.subjects.store'), [
            'name' => 'Physics',
            'slug' => 'physics',
            'level' => Subject::LEVEL_O,
            'description' => 'desc',
            'color' => '#0ea5e9',
            'icon' => 'atom',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $subjectResponse->assertRedirect(route('admin.subjects.index'));

        $subject = Subject::query()->where('slug', 'physics')->firstOrFail();

        $topicResponse = $this->actingAs($admin)->post(route('admin.topics.store'), [
            'subject_id' => $subject->id,
            'name' => 'Mechanics',
            'slug' => 'mechanics',
            'description' => 'desc',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $topicResponse->assertRedirect(route('admin.topics.index'));

        $this->assertDatabaseHas('topics', [
            'subject_id' => $subject->id,
            'slug' => 'mechanics',
        ]);
    }
}
