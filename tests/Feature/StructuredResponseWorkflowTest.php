<?php

namespace Tests\Feature;

use App\Actions\Student\BuildQuizAction;
use App\Jobs\GradeTheoryAnswerJob;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use App\Services\Import\QuestionImportService;
use App\Support\DTOs\TheoryGradeResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class StructuredResponseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_admin_can_create_and_edit_structured_response_question(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $subject = Subject::factory()->create();
        $topic = Topic::factory()->create(['subject_id' => $subject->id]);

        $this->actingAs($admin)
            ->post(route('admin.questions.store'), [
                'subject_id' => $subject->id,
                'topic_id' => $topic->id,
                'type' => Question::TYPE_STRUCTURED_RESPONSE,
                'question_text' => 'Answer all parts.',
                'difficulty' => 'medium',
                'marks' => 4,
                'is_published' => 1,
                'structured_parts' => [
                    ['part_label' => 'a', 'prompt_text' => 'Define ecosystem.', 'max_score' => 2, 'sample_answer' => 'Community and environment.', 'marking_notes' => 'Need both living and non-living mention.'],
                    ['part_label' => 'b', 'prompt_text' => 'Give one example.', 'max_score' => 2, 'sample_answer' => 'Forest ecosystem.', 'marking_notes' => null],
                ],
            ])
            ->assertRedirect();

        $question = Question::query()->firstOrFail();

        $this->assertSame(Question::TYPE_STRUCTURED_RESPONSE, $question->type);
        $this->assertCount(2, $question->structuredParts()->get());

        $this->actingAs($admin)
            ->put(route('admin.questions.update', $question), [
                'subject_id' => $subject->id,
                'topic_id' => $topic->id,
                'type' => Question::TYPE_STRUCTURED_RESPONSE,
                'question_text' => 'Answer all parts (updated).',
                'difficulty' => 'hard',
                'marks' => 5,
                'is_published' => 1,
                'structured_parts' => [
                    ['part_label' => 'a', 'prompt_text' => 'Define ecosystem.', 'max_score' => 2.5, 'sample_answer' => 'Updated sample', 'marking_notes' => 'Updated notes'],
                    ['part_label' => 'b', 'prompt_text' => 'Give one example.', 'max_score' => 2.5, 'sample_answer' => 'Forest ecosystem', 'marking_notes' => 'Any valid ecosystem'],
                ],
            ])
            ->assertRedirect(route('admin.questions.index'));

        $this->assertDatabaseHas('questions', [
            'id' => $question->id,
            'question_text' => 'Answer all parts (updated).',
            'difficulty' => 'hard',
        ]);
    }

    public function test_structured_response_quiz_flow_supports_part_answers_and_grading(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $subject = Subject::factory()->create(['is_active' => true]);

        $question = Question::query()->create([
            'subject_id' => $subject->id,
            'topic_id' => null,
            'type' => Question::TYPE_STRUCTURED_RESPONSE,
            'question_text' => 'Read and answer parts.',
            'marks' => 4,
            'is_published' => true,
        ]);

        $question->structuredParts()->createMany([
            ['part_label' => 'a', 'prompt_text' => 'Part A prompt', 'max_score' => 2, 'sample_answer' => 'Part A sample', 'sort_order' => 0],
            ['part_label' => 'b', 'prompt_text' => 'Part B prompt', 'max_score' => 2, 'sample_answer' => 'Part B sample', 'sort_order' => 1],
        ]);

        $quiz = app(BuildQuizAction::class)->execute($student, [
            'levels' => [$subject->level],
            'subject_ids' => [$subject->id],
            'mode' => Quiz::MODE_THEORY,
            'question_count' => 1,
        ]);

        $quizQuestion = $quiz->quizQuestions()->firstOrFail();

        $this->actingAs($student)
            ->putJson(route('student.quiz.answer.save', [$quiz, $quizQuestion]), [
                'structured_answers' => [
                    (string) $question->structuredParts[0]->id => 'Student answer for part A',
                    (string) $question->structuredParts[1]->id => 'Student answer for part B',
                ],
                'answered_at' => now()->toIso8601String(),
            ])
            ->assertOk();

        $this->assertDatabaseHas('student_answers', [
            'quiz_question_id' => $quizQuestion->id,
        ]);

        $answer = StudentAnswer::query()->where('quiz_question_id', $quizQuestion->id)->firstOrFail();
        $this->assertIsArray($answer->answer_json);

        $mock = Mockery::mock(\App\Services\AI\TheoryGraderService::class);
        $mock->shouldReceive('gradeBatch')->once()->andReturn([
            $answer->id.'::'.$question->structuredParts[0]->id => new TheoryGradeResult('correct', 2, 0.9, [], [], 'Great', false, ['parsed' => ['score' => 2]]),
            $answer->id.'::'.$question->structuredParts[1]->id => new TheoryGradeResult('partially_correct', 1.5, 0.8, [], [], 'Good attempt', false, ['parsed' => ['score' => 1.5]]),
        ]);
        $this->app->instance(\App\Services\AI\TheoryGraderService::class, $mock);

        dispatch_sync(new GradeTheoryAnswerJob($quiz->id, [$answer->id]));

        $answer->refresh();
        $quiz->refresh();

        $this->assertSame(StudentAnswer::STATUS_GRADED, $answer->grading_status);
        $this->assertSame('3.50', $answer->score);
        $this->assertSame(Quiz::STATUS_GRADED, $quiz->status);

        $this->actingAs($student)
            ->get(route('student.quiz.results', $quiz))
            ->assertOk()
            ->assertSee('Part A prompt')
            ->assertSee('Student answer for part A')
            ->assertSee('Part B prompt');
    }

    public function test_admin_can_download_sample_csv_templates_and_import_structured_rows(): void
    {
        Storage::fake();

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get(route('admin.imports.sample', ['template' => 'general']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $structuredCsv = implode("\n", [
            implode(',', QuestionImportService::SAMPLE_HEADERS),
            'Mathematics,Algebra,structured_response,"Solve all parts",medium,6,1,,,,,,,,,,,,SR-2001,a,"Expand (x+1)^2",2,"x^2+2x+1","Need full expansion"',
            'Mathematics,Algebra,structured_response,"Solve all parts",medium,6,1,,,,,,,,,,,,SR-2001,b,"Differentiate x^2",2,"2x","Derivative rule"',
            'Mathematics,Algebra,structured_response,"Solve all parts",medium,6,1,,,,,,,,,,,,SR-2001,c,"Integrate 2x",2,"x^2 + C","Constant needed"',
        ]);

        $file = UploadedFile::fake()->createWithContent('structured.csv', $structuredCsv);

        $this->actingAs($admin)
            ->post(route('admin.imports.store'), [
                'csv_file' => $file,
                'allow_create_subjects' => 1,
                'allow_create_topics' => 1,
            ])
            ->assertRedirect();

        $import = Import::query()->firstOrFail();

        app(QuestionImportService::class)->processImport($import->fresh(), $admin);

        $question = Question::query()->where('type', Question::TYPE_STRUCTURED_RESPONSE)->firstOrFail();
        $this->assertSame('Solve all parts', $question->question_text);
        $this->assertCount(3, $question->structuredParts()->get());

        $this->assertDatabaseHas('import_rows', [
            'import_id' => $import->id,
            'status' => ImportRow::STATUS_IMPORTED,
        ]);
    }
}
