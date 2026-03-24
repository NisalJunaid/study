<?php

namespace App\Services\Import;

use App\Actions\Admin\UpsertQuestionAction;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class QuestionImportService
{
    private const REQUIRED_COLUMNS = [
        'subject',
        'topic',
        'type',
        'question_text',
        'difficulty',
        'marks',
        'is_published',
        'option_a',
        'option_b',
        'option_c',
        'option_d',
        'option_e',
        'correct_option',
        'explanation',
        'sample_answer',
        'grading_notes',
        'keywords',
        'acceptable_phrases',
    ];

    public function __construct(
        private readonly UpsertQuestionAction $upsertQuestionAction,
    ) {}

    public function createImportFromUpload(UploadedFile $file, User $admin, bool $allowCreateSubjects, bool $allowCreateTopics): Import
    {
        return DB::transaction(function () use ($file, $admin, $allowCreateSubjects, $allowCreateTopics): Import {
            $storedPath = $file->store('imports/questions');

            $import = Import::query()->create([
                'uploaded_by' => $admin->id,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $storedPath,
                'status' => Import::STATUS_UPLOADED,
                'allow_create_subjects' => $allowCreateSubjects,
                'allow_create_topics' => $allowCreateTopics,
            ]);

            $this->validateImportRows($import);

            return $import->refresh();
        });
    }

    public function validateImportRows(Import $import): void
    {
        $import->forceFill([
            'status' => Import::STATUS_VALIDATING,
            'error_summary' => null,
            'total_rows' => 0,
            'valid_rows' => 0,
            'imported_rows' => 0,
            'failed_rows' => 0,
        ])->save();

        $import->importRows()->delete();

        $resolvedPath = Storage::path($import->file_path);
        $file = new \SplFileObject($resolvedPath);
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);

        $headerRow = $file->fgetcsv();
        $headers = collect($headerRow ?: [])->map(fn ($value) => Str::of((string) $value)->trim()->lower()->toString())->all();

        $missingHeaders = array_values(array_diff(self::REQUIRED_COLUMNS, $headers));

        if ($missingHeaders !== []) {
            $import->forceFill([
                'status' => Import::STATUS_FAILED,
                'error_summary' => 'Missing required CSV columns: '.implode(', ', $missingHeaders),
            ])->save();

            return;
        }

        $totalRows = 0;
        $validRows = 0;

        foreach ($file as $lineIndex => $rowValues) {
            if (! is_array($rowValues) || $this->isEmptyCsvRow($rowValues)) {
                continue;
            }

            $totalRows++;
            $rowNumber = $lineIndex + 1;

            $normalized = [];
            foreach ($headers as $idx => $header) {
                $normalized[$header] = isset($rowValues[$idx]) ? trim((string) $rowValues[$idx]) : '';
            }

            $errors = $this->validateRow($normalized, $import);
            $status = $errors === [] ? ImportRow::STATUS_VALID : ImportRow::STATUS_INVALID;

            if ($status === ImportRow::STATUS_VALID) {
                $validRows++;
            }

            $import->importRows()->create([
                'row_number' => $rowNumber,
                'raw_payload' => $normalized,
                'validation_errors' => $errors === [] ? null : $errors,
                'status' => $status,
            ]);
        }

        $import->forceFill([
            'status' => $validRows > 0 ? Import::STATUS_READY : Import::STATUS_FAILED,
            'total_rows' => $totalRows,
            'valid_rows' => $validRows,
            'failed_rows' => max(0, $totalRows - $validRows),
            'error_summary' => $totalRows === 0 ? 'The uploaded CSV has no data rows.' : null,
        ])->save();
    }

    public function processImport(Import $import, User $admin): void
    {
        $import->refresh();

        if (! in_array($import->status, [Import::STATUS_READY, Import::STATUS_IMPORTING], true)) {
            return;
        }

        $import->forceFill([
            'status' => Import::STATUS_IMPORTING,
            'imported_rows' => 0,
            'failed_rows' => $import->total_rows - $import->valid_rows,
            'completed_at' => null,
        ])->save();

        $importedCount = 0;
        $failedCount = max(0, $import->total_rows - $import->valid_rows);

        $validRows = $import->importRows()
            ->where('status', ImportRow::STATUS_VALID)
            ->orderBy('row_number')
            ->get();

        foreach ($validRows as $row) {
            try {
                DB::transaction(function () use ($row, $import, $admin, &$importedCount): void {
                    $payload = $this->buildQuestionPayloadFromRow($row, $import);
                    $question = $this->resolveQuestionForUpdate($payload);

                    $question = $this->upsertQuestionAction->execute(
                        payload: $payload,
                        user: $admin,
                        question: $question,
                    );

                    $row->forceFill([
                        'status' => ImportRow::STATUS_IMPORTED,
                        'validation_errors' => null,
                        'related_question_id' => $question->id,
                    ])->save();

                    $importedCount++;
                });
            } catch (Throwable $exception) {
                $failedCount++;

                $row->forceFill([
                    'status' => ImportRow::STATUS_FAILED,
                    'validation_errors' => ['import' => [$exception->getMessage()]],
                ])->save();
            }

            $import->forceFill([
                'imported_rows' => $importedCount,
                'failed_rows' => $failedCount,
            ])->save();
        }

        $status = $failedCount > 0 ? Import::STATUS_PARTIALLY_COMPLETED : Import::STATUS_COMPLETED;

        $import->forceFill([
            'status' => $status,
            'imported_rows' => $importedCount,
            'failed_rows' => $failedCount,
            'completed_at' => now(),
        ])->save();
    }

    private function validateRow(array $row, Import $import): array
    {
        $errors = [];

        $type = Str::lower($row['type'] ?? '');
        if (! in_array($type, [Question::TYPE_MCQ, Question::TYPE_THEORY], true)) {
            $errors['type'][] = 'Type must be mcq or theory.';
        }

        if (($row['question_text'] ?? '') === '') {
            $errors['question_text'][] = 'Question text is required.';
        }

        if (($row['marks'] ?? '') === '' || ! is_numeric($row['marks']) || (float) $row['marks'] < 0) {
            $errors['marks'][] = 'Marks must be a number greater than or equal to 0.';
        }

        $difficulty = Str::lower($row['difficulty'] ?? '');
        if ($difficulty !== '' && ! in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
            $errors['difficulty'][] = 'Difficulty must be easy, medium, or hard.';
        }

        if (! in_array(Str::lower($row['is_published'] ?? ''), ['0', '1', 'true', 'false', 'yes', 'no'], true)) {
            $errors['is_published'][] = 'is_published must be a boolean-like value (0/1/true/false/yes/no).';
        }

        $subjectName = trim((string) ($row['subject'] ?? ''));
        if ($subjectName === '') {
            $errors['subject'][] = 'Subject is required.';
        } else {
            $subject = $this->findSubjectByName($subjectName);
            if (! $subject && ! $import->allow_create_subjects) {
                $errors['subject'][] = 'Subject does not exist and auto-create subjects is disabled.';
            }
        }

        $topicName = trim((string) ($row['topic'] ?? ''));
        if ($topicName !== '') {
            $subject = $this->findSubjectByName($subjectName);

            if (! $subject && ! $import->allow_create_subjects) {
                $errors['topic'][] = 'Topic cannot be resolved because subject is missing.';
            } elseif ($subject) {
                $topic = Topic::query()
                    ->where('subject_id', $subject->id)
                    ->whereRaw('LOWER(name) = ?', [Str::lower($topicName)])
                    ->first();

                if (! $topic && ! $import->allow_create_topics) {
                    $errors['topic'][] = 'Topic does not exist in the subject and auto-create topics is disabled.';
                }
            }
        }

        if ($type === Question::TYPE_MCQ) {
            $options = collect(['a', 'b', 'c', 'd', 'e'])
                ->mapWithKeys(fn (string $key) => [$key => trim((string) ($row['option_'.$key] ?? ''))]);

            $nonEmptyOptionCount = $options->filter(fn (string $value) => $value !== '')->count();
            if ($nonEmptyOptionCount < 2) {
                $errors['options'][] = 'MCQ rows require at least two non-empty options.';
            }

            $correctOption = Str::lower(trim((string) ($row['correct_option'] ?? '')));
            if (! in_array($correctOption, ['a', 'b', 'c', 'd', 'e'], true)) {
                $errors['correct_option'][] = 'Correct option must be A, B, C, D, or E.';
            } elseif (($row['option_'.$correctOption] ?? '') === '') {
                $errors['correct_option'][] = 'Correct option must reference a non-empty option column.';
            }
        }

        if ($type === Question::TYPE_THEORY) {
            if (trim((string) ($row['sample_answer'] ?? '')) === '') {
                $errors['sample_answer'][] = 'Theory rows require sample_answer.';
            }
        }

        return $errors;
    }

    private function resolveQuestionForUpdate(array $payload): ?Question
    {
        return Question::query()
            ->where('subject_id', $payload['subject_id'])
            ->where('topic_id', $payload['topic_id'])
            ->where('type', $payload['type'])
            ->where('question_text', $payload['question_text'])
            ->first();
    }

    private function buildQuestionPayloadFromRow(ImportRow $row, Import $import): array
    {
        $raw = $row->raw_payload ?? [];

        $subject = $this->findOrCreateSubject((string) ($raw['subject'] ?? ''), $import->allow_create_subjects);
        $topic = $this->findOrCreateTopic((string) ($raw['topic'] ?? ''), $subject, $import->allow_create_topics);

        $type = Str::lower((string) ($raw['type'] ?? ''));

        $payload = [
            'subject_id' => $subject->id,
            'topic_id' => $topic?->id,
            'type' => $type,
            'question_text' => trim((string) ($raw['question_text'] ?? '')),
            'difficulty' => $this->normalizeNullableString((string) ($raw['difficulty'] ?? '')),
            'explanation' => $this->normalizeNullableString((string) ($raw['explanation'] ?? '')),
            'marks' => (float) ($raw['marks'] ?? 1),
            'is_published' => $this->normalizeBoolean((string) ($raw['is_published'] ?? '0')),
        ];

        if ($type === Question::TYPE_MCQ) {
            $options = collect(['a', 'b', 'c', 'd', 'e'])
                ->map(fn (string $option) => [
                    'option_key' => Str::upper($option),
                    'option_text' => trim((string) ($raw['option_'.$option] ?? '')),
                ])
                ->filter(fn (array $option) => $option['option_text'] !== '')
                ->values()
                ->all();

            $payload['options'] = $options;
            $payload['correct_option_key'] = Str::upper(trim((string) ($raw['correct_option'] ?? '')));
        }

        if ($type === Question::TYPE_THEORY) {
            $payload['sample_answer'] = trim((string) ($raw['sample_answer'] ?? ''));
            $payload['grading_notes'] = $this->normalizeNullableString((string) ($raw['grading_notes'] ?? ''));
            $payload['keywords'] = $this->normalizePipeString((string) ($raw['keywords'] ?? ''));
            $payload['acceptable_phrases'] = $this->normalizePipeString((string) ($raw['acceptable_phrases'] ?? ''));
        }

        return $payload;
    }

    private function findSubjectByName(string $subjectName): ?Subject
    {
        return Subject::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower(trim($subjectName))])
            ->first();
    }

    private function findOrCreateSubject(string $subjectName, bool $allowCreate): Subject
    {
        $subjectName = trim($subjectName);

        $subject = $this->findSubjectByName($subjectName);

        if ($subject) {
            return $subject;
        }

        if (! $allowCreate) {
            throw new \RuntimeException("Subject [{$subjectName}] was not found.");
        }

        return Subject::query()->create([
            'name' => $subjectName,
            'slug' => Str::slug($subjectName).'-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);
    }

    private function findOrCreateTopic(string $topicName, Subject $subject, bool $allowCreate): ?Topic
    {
        $topicName = trim($topicName);

        if ($topicName === '') {
            return null;
        }

        $topic = Topic::query()
            ->where('subject_id', $subject->id)
            ->whereRaw('LOWER(name) = ?', [Str::lower($topicName)])
            ->first();

        if ($topic) {
            return $topic;
        }

        if (! $allowCreate) {
            throw new \RuntimeException("Topic [{$topicName}] under subject [{$subject->name}] was not found.");
        }

        return Topic::query()->create([
            'subject_id' => $subject->id,
            'name' => $topicName,
            'slug' => Str::slug($topicName).'-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);
    }

    private function normalizeBoolean(string $value): bool
    {
        return in_array(Str::lower(trim($value)), ['1', 'true', 'yes'], true);
    }

    private function normalizeNullableString(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizePipeString(string $value): string
    {
        return collect(preg_split('/\r\n|\r|\n|\|/', $value) ?: [])
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->implode('|');
    }

    private function isEmptyCsvRow(array $row): bool
    {
        return collect($row)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->isEmpty();
    }
}
