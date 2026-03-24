<?php

namespace Tests\Feature;

use App\Jobs\ProcessQuestionImportJob;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\Question;
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
            ->post(route('admin.imports.store'), [
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
}
