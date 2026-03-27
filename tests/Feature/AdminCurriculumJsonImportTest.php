<?php

namespace Tests\Feature;

use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
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

    public function test_subject_import_validation_errors_are_visible_on_imports_page(): void
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
            ->followingRedirects()
            ->post(route('admin.imports.subjects.store'), [
                'import_form' => 'subjects',
                'subject_import_file' => $file,
            ])
            ->assertSee('Import issues found:', false)
            ->assertSee('The level field must be one of', false);
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

    public function test_topic_import_success_message_is_visible_on_imports_page(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Subject::factory()->create([
            'name' => 'Biology',
            'slug' => 'biology',
        ]);

        $file = UploadedFile::fake()->createWithContent('topics.json', json_encode([
            [
                'subject_slug' => 'biology',
                'name' => 'Genetics',
                'slug' => 'genetics',
            ],
        ], JSON_THROW_ON_ERROR));

        $this->actingAs($admin)
            ->followingRedirects()
            ->post(route('admin.imports.topics.store'), [
                'import_form' => 'topics',
                'topic_import_file' => $file,
            ])
            ->assertSee('Topics JSON imported successfully.', false);
    }

    public function test_admin_can_import_subjects_and_topics_together_from_one_json_file(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        Subject::factory()->create([
            'name' => 'Biology Legacy',
            'slug' => 'biology',
            'level' => Subject::LEVEL_A,
            'color' => '#000000',
        ]);

        $existingSubject = Subject::factory()->create([
            'name' => 'Physics',
            'slug' => 'physics',
            'level' => Subject::LEVEL_O,
        ]);

        Topic::factory()->create([
            'subject_id' => $existingSubject->id,
            'name' => 'Mechanics Old',
            'slug' => 'mechanics',
            'sort_order' => 1,
        ]);

        $payload = json_encode([
            'subjects' => [
                [
                    'name' => 'Biology',
                    'slug' => 'biology',
                    'level' => Subject::LEVEL_O,
                    'color' => '#22c55e',
                    'is_active' => true,
                    'sort_order' => 5,
                ],
                [
                    'name' => 'Chemistry',
                    'slug' => 'chemistry',
                    'level' => Subject::LEVEL_O,
                    'color' => '#16a34a',
                    'is_active' => true,
                    'sort_order' => 3,
                ],
            ],
            'topics' => [
                [
                    'subject_slug' => 'chemistry',
                    'name' => 'Atomic Structure',
                    'slug' => 'atomic-structure',
                    'is_active' => true,
                    'sort_order' => 2,
                ],
                [
                    'subject_slug' => 'physics',
                    'name' => 'Mechanics',
                    'slug' => 'mechanics',
                    'is_active' => true,
                    'sort_order' => 6,
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $file = UploadedFile::fake()->createWithContent('subject-topic.json', $payload);

        $this->actingAs($admin)
            ->post(route('admin.imports.subjects-topics.store'), [
                'subject_topic_import_file' => $file,
            ])
            ->assertRedirect(route('admin.imports.index'));

        $this->assertDatabaseHas('subjects', [
            'slug' => 'biology',
            'name' => 'Biology',
            'level' => Subject::LEVEL_O,
            'color' => '#22c55e',
            'sort_order' => 5,
        ]);

        $chemistry = Subject::query()->where('slug', 'chemistry')->firstOrFail();
        $this->assertDatabaseHas('topics', [
            'subject_id' => $chemistry->id,
            'slug' => 'atomic-structure',
            'name' => 'Atomic Structure',
            'sort_order' => 2,
        ]);

        $this->assertDatabaseHas('topics', [
            'subject_id' => $existingSubject->id,
            'slug' => 'mechanics',
            'name' => 'Mechanics',
            'sort_order' => 6,
        ]);
    }

    public function test_combined_import_rejects_invalid_subject_level(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $file = UploadedFile::fake()->createWithContent('subject-topic.json', json_encode([
            'subjects' => [
                [
                    'name' => 'Biology',
                    'slug' => 'biology',
                    'level' => 'college',
                ],
            ],
            'topics' => [],
        ], JSON_THROW_ON_ERROR));

        $this->actingAs($admin)
            ->post(route('admin.imports.subjects-topics.store'), [
                'subject_topic_import_file' => $file,
            ])
            ->assertSessionHasErrors('subjects.1');
    }

    public function test_combined_import_rejects_unresolved_topic_subject_slug(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $file = UploadedFile::fake()->createWithContent('subject-topic.json', json_encode([
            'subjects' => [],
            'topics' => [
                [
                    'subject_slug' => 'unknown-subject',
                    'name' => 'Cell Structure',
                    'slug' => 'cell-structure',
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $this->actingAs($admin)
            ->post(route('admin.imports.subjects-topics.store'), [
                'subject_topic_import_file' => $file,
            ])
            ->assertSessionHasErrors('topics.1');
    }

    public function test_combined_import_rejects_malformed_json(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $file = UploadedFile::fake()->createWithContent('subject-topic.json', '{"subjects":[{"name":"Biology"}]');

        $this->actingAs($admin)
            ->post(route('admin.imports.subjects-topics.store'), [
                'subject_topic_import_file' => $file,
            ])
            ->assertSessionHasErrors('subject_topic_import_file');
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

        $this->actingAs($admin)
            ->get(route('admin.imports.subjects-topics.sample'))
            ->assertOk()
            ->assertHeader('content-type', 'application/json')
            ->assertSee('"subjects"', false)
            ->assertSee('"topics"', false)
            ->assertSee('"subject_slug"', false);
    }

    public function test_admin_imports_page_renders_with_subject_and_topic_sample_links(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get(route('admin.imports.index'))
            ->assertOk()
            ->assertSee(route('admin.imports.subjects.sample'), false)
            ->assertSee(route('admin.imports.topics.sample'), false)
            ->assertSee(route('admin.imports.subjects-topics.sample'), false)
            ->assertSee(route('admin.imports.questions.sample', ['template' => 'general']), false);
    }

    public function test_import_routes_are_registered_with_consistent_names(): void
    {
        $this->assertTrue(Route::has('admin.imports.index'));
        $this->assertTrue(Route::has('admin.imports.show'));
        $this->assertTrue(Route::has('admin.imports.questions.store'));
        $this->assertTrue(Route::has('admin.imports.questions.sample'));
        $this->assertTrue(Route::has('admin.imports.subjects.store'));
        $this->assertTrue(Route::has('admin.imports.subjects.sample'));
        $this->assertTrue(Route::has('admin.imports.topics.store'));
        $this->assertTrue(Route::has('admin.imports.topics.sample'));
        $this->assertTrue(Route::has('admin.imports.subjects-topics.store'));
        $this->assertTrue(Route::has('admin.imports.subjects-topics.sample'));
    }

    public function test_import_routes_point_to_expected_uris_and_controller_methods(): void
    {
        $cases = [
            ['name' => 'admin.imports.index', 'method' => 'GET', 'uri' => 'admin/imports', 'action' => 'index'],
            ['name' => 'admin.imports.show', 'method' => 'GET', 'uri' => 'admin/imports/{import}', 'action' => 'show'],
            ['name' => 'admin.imports.questions.store', 'method' => 'POST', 'uri' => 'admin/imports/questions', 'action' => 'store'],
            ['name' => 'admin.imports.questions.sample', 'method' => 'GET', 'uri' => 'admin/imports/sample/questions', 'action' => 'sample'],
            ['name' => 'admin.imports.subjects.store', 'method' => 'POST', 'uri' => 'admin/imports/subjects-json', 'action' => 'storeSubjectsJson'],
            ['name' => 'admin.imports.subjects.sample', 'method' => 'GET', 'uri' => 'admin/imports/sample/subjects-json', 'action' => 'subjectSample'],
            ['name' => 'admin.imports.topics.store', 'method' => 'POST', 'uri' => 'admin/imports/topics-json', 'action' => 'storeTopicsJson'],
            ['name' => 'admin.imports.topics.sample', 'method' => 'GET', 'uri' => 'admin/imports/sample/topics-json', 'action' => 'topicSample'],
            ['name' => 'admin.imports.subjects-topics.store', 'method' => 'POST', 'uri' => 'admin/imports/subjects-topics-json', 'action' => 'storeSubjectTopicJson'],
            ['name' => 'admin.imports.subjects-topics.sample', 'method' => 'GET', 'uri' => 'admin/imports/sample/subjects-topics-json', 'action' => 'subjectTopicSample'],
        ];

        foreach ($cases as $case) {
            $route = Route::getRoutes()->getByName($case['name']);

            $this->assertNotNull($route, 'Missing route: '.$case['name']);
            $this->assertSame($case['uri'], $route->uri(), 'Unexpected URI for route: '.$case['name']);
            $this->assertContains($case['method'], $route->methods(), 'Unexpected method for route: '.$case['name']);
            $this->assertStringEndsWith('@'.$case['action'], $route->getActionName(), 'Unexpected action for route: '.$case['name']);
        }
    }


    public function test_import_routes_use_controller_actions_for_route_cache_compatibility(): void
    {
        $routes = [
            'admin.imports.index' => 'index',
            'admin.imports.show' => 'show',
            'admin.imports.questions.store' => 'store',
            'admin.imports.questions.sample' => 'sample',
            'admin.imports.subjects.store' => 'storeSubjectsJson',
            'admin.imports.subjects.sample' => 'subjectSample',
            'admin.imports.topics.store' => 'storeTopicsJson',
            'admin.imports.topics.sample' => 'topicSample',
            'admin.imports.subjects-topics.store' => 'storeSubjectTopicJson',
            'admin.imports.subjects-topics.sample' => 'subjectTopicSample',
        ];

        foreach ($routes as $name => $method) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, 'Missing route: '.$name);
            $this->assertStringContainsString('App\\Http\\Controllers\\Admin\\ImportController@'.$method, $route->getActionName());
            $this->assertArrayNotHasKey('uses', array_filter($route->getAction(), fn ($value, $key) => $key === 'uses' && $value instanceof \Closure, ARRAY_FILTER_USE_BOTH));
        }
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
