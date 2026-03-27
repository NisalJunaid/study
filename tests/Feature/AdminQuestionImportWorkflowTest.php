<?php

namespace Tests\Feature;

use App\Jobs\ProcessQuestionImportJob;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\Question;
use App\Models\StructuredQuestionPart;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use App\Services\Import\QuestionImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminQuestionImportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_admin_can_upload_and_preview_csv_with_row_level_validation_errors(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $subject = Subject::factory()->create(['name' => 'Mathematics']);
        Topic::factory()->create(['subject_id' => $subject->id, 'name' => 'Algebra']);

        $csv = <<<'CSV'
subject,topic,type,question_text,difficulty,marks,is_published,option_a,option_b,option_c,option_d,option_e,correct_option,explanation,sample_answer,grading_notes,keywords,acceptable_phrases
Mathematics,Algebra,mcq,What is 2+2?,easy,1,1,3,4,5,,,B,Basic arithmetic,,,,
Mathematics,Algebra,theory,Explain punctuation importance,medium,3,1,,,,,,,,,Expected punctuation clarity,
CSV;

        $file = UploadedFile::fake()->createWithContent('questions.csv', $csv);

        $response = $this->actingAs($admin)
            ->post(route('admin.imports.questions.store'), [
                'csv_file' => $file,
                'allow_create_subjects' => false,
                'allow_create_topics' => false,
            ]);

        $import = Import::query()->firstOrFail();

        $response->assertRedirect(route('admin.imports.show', $import));

        $import->refresh();

        $this->assertSame(Import::STATUS_READY, $import->status);
        $this->assertGreaterThanOrEqual(2, $import->total_rows);
        $this->assertSame(1, $import->valid_rows);
        $this->assertGreaterThanOrEqual(1, $import->failed_rows);

        $validRow = ImportRow::query()->where('status', ImportRow::STATUS_VALID)->first();
        $invalidRow = ImportRow::query()->where('status', ImportRow::STATUS_INVALID)->first();

        $this->assertNotNull($validRow);
        $this->assertNotNull($invalidRow);
        $this->assertNotEmpty($invalidRow->validation_errors);
    }

    public function test_admin_can_upload_and_preview_json_with_mcq_theory_and_structured_response_rows(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $subject = Subject::factory()->create(['name' => 'Mathematics']);
        Topic::factory()->create(['subject_id' => $subject->id, 'name' => 'Algebra']);

        $english = Subject::factory()->create(['name' => 'English']);
        Topic::factory()->create(['subject_id' => $english->id, 'name' => 'Essay Writing']);

        $json = json_encode([
            'questions' => [
                [
                    'type' => 'mcq',
                    'subject' => 'Mathematics',
                    'topic' => 'Algebra',
                    'difficulty' => 'easy',
                    'marks' => 1,
                    'question_text' => 'What is 2 + 2?',
                    'is_published' => true,
                    'options' => [
                        ['option_key' => 'A', 'option_text' => '3'],
                        ['option_key' => 'B', 'option_text' => '4'],
                        ['option_key' => 'C', 'option_text' => '5'],
                    ],
                    'correct_option' => 'B',
                ],
                [
                    'type' => 'theory',
                    'subject' => 'English',
                    'topic' => 'Essay Writing',
                    'difficulty' => 'medium',
                    'marks' => 3,
                    'question_text' => 'Explain why punctuation is important.',
                    'is_published' => true,
                    'sample_answer' => 'Punctuation improves clarity.',
                    'grading_notes' => 'Mention clarity and structure.',
                ],
                [
                    'type' => 'structured_response',
                    'subject' => 'English',
                    'topic' => 'Essay Writing',
                    'difficulty' => 'medium',
                    'marks' => 4,
                    'question_text' => 'Answer all parts.',
                    'is_published' => true,
                    'question_group_key' => 'SR-JSON-1',
                    'structured_parts' => [
                        [
                            'label' => 'a',
                            'prompt_text' => 'Identify one key point.',
                            'max_score' => 2,
                            'sample_answer' => 'The key point is...',
                        ],
                        [
                            'label' => 'b',
                            'prompt_text' => 'Explain your reason.',
                            'max_score' => 2,
                            'sample_answer' => 'Because...',
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $file = UploadedFile::fake()->createWithContent('questions.json', $json);

        $response = $this->actingAs($admin)->post(route('admin.imports.questions.store'), [
            'import_file' => $file,
            'allow_create_subjects' => false,
            'allow_create_topics' => false,
        ]);

        $import = Import::query()->firstOrFail();

        $response->assertRedirect(route('admin.imports.show', $import));
        $import->refresh();

        $this->assertSame(Import::STATUS_READY, $import->status);
        $this->assertSame(4, $import->total_rows);
        $this->assertSame(4, $import->valid_rows);
        $this->assertSame(0, $import->failed_rows);
    }

    public function test_admin_json_upload_fails_for_malformed_payload(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $file = UploadedFile::fake()->createWithContent('bad.json', '{"questions": [}');

        $this->actingAs($admin)->post(route('admin.imports.questions.store'), [
            'import_file' => $file,
        ]);

        $import = Import::query()->firstOrFail()->refresh();

        $this->assertSame(Import::STATUS_FAILED, $import->status);
        $this->assertStringContainsString('Malformed JSON', (string) $import->error_summary);
    }

    public function test_admin_json_upload_marks_rows_invalid_for_missing_required_fields_and_unsupported_types(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Subject::factory()->create(['name' => 'Mathematics']);

        $json = json_encode([
            'questions' => [
                [
                    'type' => 'mcq',
                    'subject' => 'Mathematics',
                    'question_text' => 'Invalid mcq with one option',
                    'marks' => 1,
                    'is_published' => true,
                    'options' => [
                        ['option_key' => 'A', 'option_text' => 'Only option'],
                    ],
                    'correct_option' => 'A',
                ],
                [
                    'type' => 'matching',
                    'subject' => 'Mathematics',
                    'question_text' => 'Unsupported type',
                    'marks' => 1,
                    'is_published' => true,
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $file = UploadedFile::fake()->createWithContent('invalid.json', $json);

        $this->actingAs($admin)->post(route('admin.imports.questions.store'), [
            'import_file' => $file,
        ]);

        $import = Import::query()->firstOrFail()->refresh();
        $this->assertSame(Import::STATUS_FAILED, $import->status);
        $this->assertSame(2, $import->failed_rows);

        $errors = $import->importRows()->orderBy('row_number')->pluck('validation_errors')->all();
        $flatErrors = json_encode($errors, JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('MCQ rows require at least two non-empty options', (string) $flatErrors);
        $this->assertStringContainsString('Type must be mcq, theory, or structured_response', (string) $flatErrors);
    }

    public function test_admin_can_download_json_samples(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin)->get(route('admin.imports.questions.sample', [
            'format' => 'json',
            'template' => 'structured_response',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');
        $response->assertSee('"questions"', false);
        $response->assertSee('"structured_parts"', false);
    }

    public function test_confirming_import_dispatches_job_and_processes_valid_rows_without_failing_entire_run(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $subject = Subject::factory()->create(['name' => 'Mathematics']);
        Topic::factory()->create(['subject_id' => $subject->id, 'name' => 'Algebra']);

        $import = Import::query()->create([
            'uploaded_by' => $admin->id,
            'file_name' => 'questions.csv',
            'file_path' => 'imports/questions/questions.csv',
            'status' => Import::STATUS_READY,
            'total_rows' => 2,
            'valid_rows' => 2,
        ]);

        $import->importRows()->create([
            'row_number' => 2,
            'status' => ImportRow::STATUS_VALID,
            'raw_payload' => [
                'subject' => 'Mathematics',
                'topic' => 'Algebra',
                'type' => 'mcq',
                'question_text' => 'Solve: 5 + 5',
                'difficulty' => 'easy',
                'marks' => '1',
                'is_published' => '1',
                'option_a' => '8',
                'option_b' => '10',
                'option_c' => '',
                'option_d' => '',
                'option_e' => '',
                'correct_option' => 'b',
                'explanation' => '5 + 5 is 10',
                'sample_answer' => '',
                'grading_notes' => '',
                'keywords' => '',
                'acceptable_phrases' => '',
            ],
        ]);

        $import->importRows()->create([
            'row_number' => 3,
            'status' => ImportRow::STATUS_VALID,
            'raw_payload' => [
                'subject' => 'Unknown Subject',
                'topic' => 'Algebra',
                'type' => 'mcq',
                'question_text' => 'Explain algebraic substitution.',
                'difficulty' => 'medium',
                'marks' => '2',
                'is_published' => '1',
                'option_a' => '',
                'option_b' => '',
                'option_c' => '',
                'option_d' => '',
                'option_e' => '',
                'correct_option' => '',
                'explanation' => '',
                'sample_answer' => '',
                'grading_notes' => '',
                'keywords' => '',
                'acceptable_phrases' => '',
            ],
        ]);

        Bus::fake();

        $this->actingAs($admin)
            ->post(route('admin.imports.confirm', $import))
            ->assertRedirect(route('admin.imports.show', $import));

        Bus::assertDispatched(ProcessQuestionImportJob::class);

        app(QuestionImportService::class)->processImport($import->fresh(), $admin);

        $import->refresh();

        $this->assertSame(Import::STATUS_PARTIALLY_COMPLETED, $import->status);
        $this->assertSame(1, $import->imported_rows);
        $this->assertSame(1, $import->failed_rows);
        $this->assertNotNull($import->completed_at);

        $this->assertDatabaseHas('questions', [
            'subject_id' => $subject->id,
            'question_text' => 'Solve: 5 + 5',
            'type' => Question::TYPE_MCQ,
        ]);

        $this->assertDatabaseHas('import_rows', [
            'import_id' => $import->id,
            'row_number' => 2,
            'status' => ImportRow::STATUS_IMPORTED,
        ]);

        $this->assertDatabaseHas('import_rows', [
            'import_id' => $import->id,
            'row_number' => 3,
            'status' => ImportRow::STATUS_FAILED,
        ]);
    }

    public function test_processing_json_import_persists_all_structured_parts_and_keeps_mcq_and_theory_imports_working(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $biology = Subject::factory()->create(['name' => 'Biology']);
        Topic::factory()->create(['subject_id' => $biology->id, 'name' => 'Characteristics of Living Organisms']);

        $math = Subject::factory()->create(['name' => 'Mathematics']);
        Topic::factory()->create(['subject_id' => $math->id, 'name' => 'Algebra']);

        $english = Subject::factory()->create(['name' => 'English']);
        Topic::factory()->create(['subject_id' => $english->id, 'name' => 'Essay Writing']);

        $json = json_encode([
            'questions' => [
                [
                    'type' => 'mcq',
                    'subject' => 'Mathematics',
                    'topic' => 'Algebra',
                    'difficulty' => 'easy',
                    'marks' => 1,
                    'question_text' => 'What is 2 + 2?',
                    'is_published' => true,
                    'options' => [
                        ['option_key' => 'A', 'option_text' => '3'],
                        ['option_key' => 'B', 'option_text' => '4'],
                        ['option_key' => 'C', 'option_text' => '5'],
                    ],
                    'correct_option' => 'B',
                ],
                [
                    'type' => 'theory',
                    'subject' => 'English',
                    'topic' => 'Essay Writing',
                    'difficulty' => 'medium',
                    'marks' => 3,
                    'question_text' => 'Explain why punctuation is important in writing.',
                    'is_published' => true,
                    'sample_answer' => 'Punctuation helps clarify meaning, structure sentences, and guide pauses.',
                    'grading_notes' => 'Expect meaning, clarity, and sentence structure.',
                ],
                [
                    'type' => 'structured_response',
                    'subject' => 'Biology',
                    'topic' => 'Characteristics of Living Organisms',
                    'difficulty' => 'easy',
                    'marks' => 5,
                    'question_text' => 'A student observes a plant growing towards sunlight.',
                    'is_published' => true,
                    'question_group_key' => 'BIO-SR-1001',
                    'structured_parts' => [
                        [
                            'label' => 'a',
                            'prompt_text' => 'State the name of this response.',
                            'max_score' => 1,
                            'sample_answer' => 'Phototropism',
                            'marking_notes' => 'Accept positive phototropism.',
                        ],
                        [
                            'label' => 'b',
                            'prompt_text' => 'Explain why this response is important for the plant.',
                            'max_score' => 2,
                            'sample_answer' => 'It allows the plant to grow towards light so that it can absorb more light for photosynthesis.',
                            'marking_notes' => 'Award 1 mark for grows towards light and 1 mark for more photosynthesis.',
                        ],
                        [
                            'label' => 'c',
                            'prompt_text' => 'Name two other characteristics of living organisms.',
                            'max_score' => 2,
                            'sample_answer' => 'Respiration and growth',
                            'marking_notes' => 'Accept any two valid characteristics such as movement, respiration, sensitivity, growth, reproduction, excretion or nutrition.',
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $file = UploadedFile::fake()->createWithContent('questions.json', $json);

        $this->actingAs($admin)->post(route('admin.imports.questions.store'), [
            'import_file' => $file,
            'allow_create_subjects' => false,
            'allow_create_topics' => false,
        ])->assertRedirect();

        $import = Import::query()->firstOrFail();
        app(QuestionImportService::class)->processImport($import->fresh(), $admin);

        $structuredQuestion = Question::query()
            ->where('type', Question::TYPE_STRUCTURED_RESPONSE)
            ->where('question_text', 'A student observes a plant growing towards sunlight.')
            ->firstOrFail();

        $parts = $structuredQuestion->structuredParts()->orderBy('sort_order')->get();

        $this->assertCount(3, $parts);
        $this->assertSame(['a', 'b', 'c'], $parts->pluck('part_label')->all());
        $this->assertSame('State the name of this response.', $parts[0]->prompt_text);
        $this->assertSame('1.00', $parts[0]->max_score);
        $this->assertSame('Phototropism', $parts[0]->sample_answer);
        $this->assertSame('Accept positive phototropism.', $parts[0]->marking_notes);
        $this->assertSame('Explain why this response is important for the plant.', $parts[1]->prompt_text);
        $this->assertSame('2.00', $parts[1]->max_score);
        $this->assertSame('Name two other characteristics of living organisms.', $parts[2]->prompt_text);

        $this->assertDatabaseHas('questions', [
            'type' => Question::TYPE_MCQ,
            'question_text' => 'What is 2 + 2?',
        ]);

        $this->assertDatabaseHas('questions', [
            'type' => Question::TYPE_THEORY,
            'question_text' => 'Explain why punctuation is important in writing.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.questions.edit', $structuredQuestion))
            ->assertOk()
            ->assertSee('State the name of this response.')
            ->assertSee('Explain why this response is important for the plant.')
            ->assertSee('Name two other characteristics of living organisms.');
    }

    public function test_processing_structured_json_update_replaces_question_with_full_part_set_instead_of_last_part_only(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $biology = Subject::factory()->create(['name' => 'Biology']);
        $topic = Topic::factory()->create([
            'subject_id' => $biology->id,
            'name' => 'Characteristics of Living Organisms',
        ]);

        $question = Question::factory()->create([
            'subject_id' => $biology->id,
            'topic_id' => $topic->id,
            'type' => Question::TYPE_STRUCTURED_RESPONSE,
            'question_text' => 'A student observes a plant growing towards sunlight.',
            'marks' => 1,
        ]);

        StructuredQuestionPart::query()->create([
            'question_id' => $question->id,
            'part_label' => 'legacy',
            'prompt_text' => 'Legacy prompt',
            'max_score' => 1,
            'sample_answer' => 'Legacy answer',
            'marking_notes' => 'Legacy notes',
            'sort_order' => 0,
        ]);

        $json = json_encode([
            'questions' => [
                [
                    'type' => 'structured_response',
                    'subject' => 'Biology',
                    'topic' => 'Characteristics of Living Organisms',
                    'difficulty' => 'easy',
                    'marks' => 5,
                    'question_text' => 'A student observes a plant growing towards sunlight.',
                    'is_published' => true,
                    'question_group_key' => 'BIO-SR-1001',
                    'structured_parts' => [
                        [
                            'label' => 'a',
                            'prompt_text' => 'State the name of this response.',
                            'max_score' => 1,
                            'sample_answer' => 'Phototropism',
                            'marking_notes' => 'Accept positive phototropism.',
                        ],
                        [
                            'label' => 'b',
                            'prompt_text' => 'Explain why this response is important for the plant.',
                            'max_score' => 2,
                            'sample_answer' => 'It allows the plant to grow towards light so that it can absorb more light for photosynthesis.',
                            'marking_notes' => 'Award 1 mark for grows towards light and 1 mark for more photosynthesis.',
                        ],
                        [
                            'label' => 'c',
                            'prompt_text' => 'Name two other characteristics of living organisms.',
                            'max_score' => 2,
                            'sample_answer' => 'Respiration and growth',
                            'marking_notes' => 'Accept any two valid characteristics such as movement, respiration, sensitivity, growth, reproduction, excretion or nutrition.',
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $file = UploadedFile::fake()->createWithContent('questions-update.json', $json);

        $this->actingAs($admin)->post(route('admin.imports.questions.store'), [
            'import_file' => $file,
            'allow_create_subjects' => false,
            'allow_create_topics' => false,
        ])->assertRedirect();

        $import = Import::query()->latest('id')->firstOrFail();
        app(QuestionImportService::class)->processImport($import->fresh(), $admin);

        $question->refresh();
        $parts = $question->structuredParts()->orderBy('sort_order')->get();

        $this->assertCount(3, $parts);
        $this->assertSame(['a', 'b', 'c'], $parts->pluck('part_label')->all());
        $this->assertDatabaseMissing('structured_question_parts', [
            'question_id' => $question->id,
            'part_label' => 'legacy',
        ]);
    }
}
