<?php

namespace App\Services\Import;

use App\Actions\Admin\UpsertQuestionAction;
use App\Events\ImportProgressUpdated;
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
use JsonException;
use Throwable;

class QuestionImportService
{
    private const FORMAT_CSV = 'csv';
    private const FORMAT_JSON = 'json';

    private const REQUIRED_COLUMNS = [
        'subject',
        'topic',
        'type',
        'question_text',
        'difficulty',
        'marks',
        'is_published',
    ];

    public const SAMPLE_HEADERS = [
        'subject', 'topic', 'type', 'question_text', 'difficulty', 'marks', 'is_published',
        'option_a', 'option_b', 'option_c', 'option_d', 'option_e', 'correct_option', 'explanation',
        'sample_answer', 'grading_notes', 'keywords', 'acceptable_phrases',
        'question_group_key', 'part_label', 'part_prompt', 'part_marks', 'part_sample_answer', 'part_marking_notes',
    ];

    private const JSON_ROOT_KEY = 'questions';

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

    public function sampleRows(string $template): array
    {
        if ($template === 'structured_response') {
            return [
                self::SAMPLE_HEADERS,
                ['English', 'Essay Writing', 'structured_response', 'Read the passage and answer all parts.', 'medium', '6', '1', '', '', '', '', '', '', '', '', '', '', '', 'SR-1001', 'a', 'Identify two persuasive techniques used in paragraph 1.', '2', 'Technique one and technique two with evidence.', 'Award 1 mark per correctly identified technique with quote.'],
                ['English', 'Essay Writing', 'structured_response', 'Read the passage and answer all parts.', 'medium', '6', '1', '', '', '', '', '', '', '', '', '', '', '', 'SR-1001', 'b', 'Explain how tone changes in paragraph 3.', '2', 'Tone shifts from neutral to urgent.', 'Require explanation of shift and textual support.'],
                ['English', 'Essay Writing', 'structured_response', 'Read the passage and answer all parts.', 'medium', '6', '1', '', '', '', '', '', '', '', '', '', '', '', 'SR-1001', 'c', 'State the writer’s main conclusion.', '2', 'Conclusion argues for regular reading practice.', 'Accept paraphrased equivalent statements.'],
            ];
        }

        return [
            self::SAMPLE_HEADERS,
            ['Mathematics', 'Algebra', 'mcq', 'What is 2x when x = 3?', 'easy', '1', '1', '3', '5', '6', '8', '', 'C', '2 multiplied by 3 equals 6', '', '', '', '', '', '', '', '', '', ''],
            ['English', 'Essay Writing', 'theory', 'Explain why punctuation is important in writing.', 'medium', '3', '1', '', '', '', '', '', '', '', 'Punctuation helps clarify meaning, structure sentences, and guide pauses.', 'Expect meaning, clarity, and sentence structure.', 'clarity|meaning|structure', 'guides pauses|separates ideas', '', '', '', '', '', ''],
        ];
    }

    public function sampleJson(string $template = 'all'): array
    {
        $samples = $this->jsonQuestionSamples();

        return [
            self::JSON_ROOT_KEY => match ($template) {
                'mcq' => [$samples['mcq']],
                'theory' => [$samples['theory']],
                'structured_response' => [$samples['structured_response']],
                default => array_values($samples),
            },
        ];
    }

    public function sampleJsonStrings(): array
    {
        return [
            'mcq' => json_encode($this->sampleJson('mcq'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'theory' => json_encode($this->sampleJson('theory'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'structured_response' => json_encode($this->sampleJson('structured_response'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ];
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
        ImportProgressUpdated::dispatch($import->id);

        $import->importRows()->delete();

        $format = $this->detectFileFormat($import->file_name);

        if ($format === self::FORMAT_JSON) {
            $this->validateJsonImportRows($import);

            return;
        }

        $this->validateCsvImportRows($import);
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
        ImportProgressUpdated::dispatch($import->id);

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
            ImportProgressUpdated::dispatch($import->id);
        }

        $status = $failedCount > 0 ? Import::STATUS_PARTIALLY_COMPLETED : Import::STATUS_COMPLETED;

        $import->forceFill([
            'status' => $status,
            'imported_rows' => $importedCount,
            'failed_rows' => $failedCount,
            'completed_at' => now(),
        ])->save();
        ImportProgressUpdated::dispatch($import->id);
    }

    private function validateRow(array $row, Import $import): array
    {
        $errors = [];

        $type = Str::lower($row['type'] ?? '');
        if (! in_array($type, [Question::TYPE_MCQ, Question::TYPE_THEORY, Question::TYPE_STRUCTURED_RESPONSE], true)) {
            $errors['type'][] = 'Type must be mcq, theory, or structured_response.';
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

        if ($type === Question::TYPE_THEORY && trim((string) ($row['sample_answer'] ?? '')) === '') {
            $errors['sample_answer'][] = 'Theory rows require sample_answer.';
        }

        if ($type === Question::TYPE_STRUCTURED_RESPONSE) {
            if (trim((string) ($row['part_label'] ?? '')) === '') {
                $errors['part_label'][] = 'Structured rows require part_label.';
            }

            if (trim((string) ($row['part_prompt'] ?? '')) === '') {
                $errors['part_prompt'][] = 'Structured rows require part_prompt.';
            }

            if (! is_numeric($row['part_marks'] ?? null) || (float) $row['part_marks'] <= 0) {
                $errors['part_marks'][] = 'Structured rows require part_marks > 0.';
            }
        }

        return $errors;
    }

    private function validateCsvImportRows(Import $import): void
    {
        $resolvedPath = Storage::path($import->file_path);
        $file = new \SplFileObject($resolvedPath);
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);

        $headerRow = $file->fgetcsv();
        $headers = collect($headerRow ?: [])->map(fn ($value) => Str::of((string) $value)->trim()->lower()->toString())->all();

        $missingHeaders = array_values(array_diff(self::REQUIRED_COLUMNS, $headers));
        if ($missingHeaders !== []) {
            $this->markImportAsFailed($import, 'Missing required CSV columns: '.implode(', ', $missingHeaders));

            return;
        }

        $rowCandidates = [];
        foreach ($file as $lineIndex => $rowValues) {
            if (! is_array($rowValues) || $this->isEmptyCsvRow($rowValues)) {
                continue;
            }

            $normalized = [];
            foreach ($headers as $idx => $header) {
                $normalized[$header] = isset($rowValues[$idx]) ? trim((string) $rowValues[$idx]) : '';
            }

            $rowCandidates[] = [
                'row_number' => $lineIndex + 1,
                'payload' => $normalized,
            ];
        }

        $this->persistValidatedRows(
            import: $import,
            rowCandidates: $rowCandidates,
            emptyMessage: 'The uploaded CSV has no data rows.',
        );
    }

    private function validateJsonImportRows(Import $import): void
    {
        $resolvedPath = Storage::path($import->file_path);
        $rawJson = file_get_contents($resolvedPath);

        try {
            $decoded = json_decode((string) $rawJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->markImportAsFailed($import, 'Malformed JSON: '.$exception->getMessage());

            return;
        }

        if (! is_array($decoded) || ! isset($decoded[self::JSON_ROOT_KEY]) || ! is_array($decoded[self::JSON_ROOT_KEY])) {
            $this->markImportAsFailed($import, 'JSON must be an object with a "questions" array.');

            return;
        }

        $rowCandidates = [];
        $rowNumber = 1;

        foreach ($decoded[self::JSON_ROOT_KEY] as $questionIndex => $questionPayload) {
            $questionNumber = $questionIndex + 1;

            if (! is_array($questionPayload)) {
                $rowCandidates[] = [
                    'row_number' => $rowNumber++,
                    'payload' => ['type' => '', 'question_text' => '', 'subject' => '', 'topic' => ''],
                    'pre_errors' => ['question' => ["Question #{$questionNumber} must be an object."]],
                ];
                continue;
            }

            $normalizedRows = $this->normalizeJsonQuestionRows($questionPayload, $questionNumber);
            foreach ($normalizedRows as $normalizedRow) {
                $rowCandidates[] = [
                    'row_number' => $rowNumber++,
                    'payload' => $normalizedRow['payload'],
                    'pre_errors' => $normalizedRow['pre_errors'] ?? [],
                ];
            }
        }

        $this->persistValidatedRows(
            import: $import,
            rowCandidates: $rowCandidates,
            emptyMessage: 'The uploaded JSON has no question records.',
        );
    }

    private function persistValidatedRows(Import $import, array $rowCandidates, string $emptyMessage): void
    {
        $totalRows = 0;
        $validRows = 0;

        foreach ($rowCandidates as $candidate) {
            $payload = $candidate['payload'];
            $errors = $candidate['pre_errors'] ?? [];

            $validationErrors = $this->validateRow($payload, $import);
            foreach ($validationErrors as $field => $messages) {
                $errors[$field] = array_values(array_unique(array_merge($errors[$field] ?? [], $messages)));
            }

            $status = $errors === [] ? ImportRow::STATUS_VALID : ImportRow::STATUS_INVALID;

            $import->importRows()->create([
                'row_number' => $candidate['row_number'],
                'raw_payload' => $payload,
                'validation_errors' => $errors === [] ? null : $errors,
                'status' => $status,
            ]);

            $totalRows++;
            if ($status === ImportRow::STATUS_VALID) {
                $validRows++;
            }
        }

        $import->forceFill([
            'status' => $validRows > 0 ? Import::STATUS_READY : Import::STATUS_FAILED,
            'total_rows' => $totalRows,
            'valid_rows' => $validRows,
            'failed_rows' => max(0, $totalRows - $validRows),
            'error_summary' => $totalRows === 0 ? $emptyMessage : null,
        ])->save();
        ImportProgressUpdated::dispatch($import->id);
    }

    private function normalizeJsonQuestionRows(array $questionPayload, int $questionNumber): array
    {
        $base = [
            'subject' => trim((string) ($questionPayload['subject'] ?? '')),
            'topic' => trim((string) ($questionPayload['topic'] ?? '')),
            'type' => Str::lower(trim((string) ($questionPayload['type'] ?? ''))),
            'question_text' => trim((string) ($questionPayload['question_text'] ?? '')),
            'difficulty' => trim((string) ($questionPayload['difficulty'] ?? '')),
            'marks' => (string) ($questionPayload['marks'] ?? ''),
            'is_published' => $this->normalizeJsonBooleanString($questionPayload['is_published'] ?? null),
            'explanation' => trim((string) ($questionPayload['explanation'] ?? '')),
            'sample_answer' => trim((string) ($questionPayload['sample_answer'] ?? '')),
            'grading_notes' => trim((string) ($questionPayload['grading_notes'] ?? '')),
            'keywords' => $this->normalizeJsonList($questionPayload['keywords'] ?? ''),
            'acceptable_phrases' => $this->normalizeJsonList($questionPayload['acceptable_phrases'] ?? ''),
            'question_group_key' => trim((string) ($questionPayload['question_group_key'] ?? "json-{$questionNumber}")),
        ];

        if ($base['type'] === Question::TYPE_MCQ) {
            $options = $this->normalizeJsonMcqOptions($questionPayload['options'] ?? []);
            foreach (['a', 'b', 'c', 'd', 'e'] as $key) {
                $base['option_'.$key] = $options[$key] ?? '';
            }
            $base['correct_option'] = Str::lower(trim((string) ($questionPayload['correct_option'] ?? $questionPayload['correct_option_key'] ?? '')));
        }

        if ($base['type'] === Question::TYPE_STRUCTURED_RESPONSE) {
            $parts = $questionPayload['structured_parts'] ?? null;
            if (! is_array($parts) || $parts === []) {
                return [[
                    'payload' => array_merge($base, [
                        'part_label' => '',
                        'part_prompt' => '',
                        'part_marks' => '',
                        'part_sample_answer' => '',
                        'part_marking_notes' => '',
                    ]),
                    'pre_errors' => ['structured_parts' => ['Structured response questions require a non-empty structured_parts array.']],
                ]];
            }

            return collect($parts)->map(function ($part) use ($base): array {
                $partData = is_array($part) ? $part : [];

                return [
                    'payload' => array_merge($base, [
                        'part_label' => trim((string) ($partData['label'] ?? $partData['part_label'] ?? '')),
                        'part_prompt' => trim((string) ($partData['prompt_text'] ?? $partData['part_prompt'] ?? '')),
                        'part_marks' => (string) ($partData['max_score'] ?? $partData['part_marks'] ?? ''),
                        'part_sample_answer' => trim((string) ($partData['sample_answer'] ?? $partData['part_sample_answer'] ?? '')),
                        'part_marking_notes' => trim((string) ($partData['marking_notes'] ?? $partData['part_marking_notes'] ?? '')),
                    ]),
                ];
            })->all();
        }

        return [[
            'payload' => array_merge($base, [
                'option_a' => $base['option_a'] ?? '',
                'option_b' => $base['option_b'] ?? '',
                'option_c' => $base['option_c'] ?? '',
                'option_d' => $base['option_d'] ?? '',
                'option_e' => $base['option_e'] ?? '',
                'correct_option' => $base['correct_option'] ?? '',
                'part_label' => '',
                'part_prompt' => '',
                'part_marks' => '',
                'part_sample_answer' => '',
                'part_marking_notes' => '',
            ]),
        ]];
    }

    private function normalizeJsonMcqOptions(mixed $options): array
    {
        if (! is_array($options)) {
            return [];
        }

        $normalized = [];
        foreach ($options as $index => $option) {
            if (is_array($option)) {
                $optionKey = Str::lower(trim((string) ($option['option_key'] ?? $option['key'] ?? '')));
                $optionText = trim((string) ($option['option_text'] ?? $option['text'] ?? ''));
            } else {
                $optionKey = chr(ord('a') + $index);
                $optionText = trim((string) $option);
            }

            if (in_array($optionKey, ['a', 'b', 'c', 'd', 'e'], true)) {
                $normalized[$optionKey] = $optionText;
            }
        }

        return $normalized;
    }

    private function normalizeJsonList(mixed $value): string
    {
        if (is_array($value)) {
            return collect($value)->map(fn ($item) => trim((string) $item))->filter()->implode('|');
        }

        return trim((string) $value);
    }

    private function normalizeJsonBooleanString(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return trim((string) ($value ?? '0'));
    }

    private function detectFileFormat(string $fileName): string
    {
        return Str::lower((string) pathinfo($fileName, PATHINFO_EXTENSION)) === self::FORMAT_JSON
            ? self::FORMAT_JSON
            : self::FORMAT_CSV;
    }

    private function markImportAsFailed(Import $import, string $errorSummary): void
    {
        $import->forceFill([
            'status' => Import::STATUS_FAILED,
            'error_summary' => $errorSummary,
            'total_rows' => 0,
            'valid_rows' => 0,
            'failed_rows' => 0,
        ])->save();
        ImportProgressUpdated::dispatch($import->id);
    }

    private function jsonQuestionSamples(): array
    {
        return [
            'mcq' => [
                'type' => 'mcq',
                'subject' => 'Mathematics',
                'topic' => 'Algebra',
                'difficulty' => 'easy',
                'marks' => 1,
                'question_text' => 'What is 2x when x = 3?',
                'explanation' => '2 multiplied by 3 equals 6.',
                'is_published' => true,
                'options' => [
                    ['option_key' => 'A', 'option_text' => '3'],
                    ['option_key' => 'B', 'option_text' => '5'],
                    ['option_key' => 'C', 'option_text' => '6'],
                    ['option_key' => 'D', 'option_text' => '8'],
                ],
                'correct_option' => 'C',
            ],
            'theory' => [
                'type' => 'theory',
                'subject' => 'English',
                'topic' => 'Essay Writing',
                'difficulty' => 'medium',
                'marks' => 3,
                'question_text' => 'Explain why punctuation is important in writing.',
                'is_published' => true,
                'sample_answer' => 'Punctuation clarifies meaning, structures sentences, and guides pauses for readers.',
                'grading_notes' => 'Award for references to clarity, meaning, and sentence flow.',
                'keywords' => ['clarity', 'meaning', 'structure'],
                'acceptable_phrases' => ['guides pauses', 'separates ideas'],
            ],
            'structured_response' => [
                'type' => 'structured_response',
                'subject' => 'English',
                'topic' => 'Essay Writing',
                'difficulty' => 'medium',
                'marks' => 6,
                'question_text' => 'Read the passage and answer all parts.',
                'is_published' => true,
                'question_group_key' => 'SR-1001',
                'structured_parts' => [
                    [
                        'label' => 'a',
                        'prompt_text' => 'Identify two persuasive techniques used in paragraph 1.',
                        'max_score' => 2,
                        'sample_answer' => 'Technique one and technique two with evidence.',
                        'marking_notes' => 'Award 1 mark per correctly identified technique with quote.',
                    ],
                    [
                        'label' => 'b',
                        'prompt_text' => 'Explain how tone changes in paragraph 3.',
                        'max_score' => 2,
                        'sample_answer' => 'Tone shifts from neutral to urgent.',
                        'marking_notes' => 'Require explanation of shift and textual support.',
                    ],
                    [
                        'label' => 'c',
                        'prompt_text' => 'State the writer’s main conclusion.',
                        'max_score' => 2,
                        'sample_answer' => 'Conclusion argues for regular reading practice.',
                        'marking_notes' => 'Accept paraphrased equivalent statements.',
                    ],
                ],
            ],
        ];
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

        if ($type === Question::TYPE_STRUCTURED_RESPONSE) {
            $groupKey = $this->structuredGroupKey($raw);
            $groupRows = $row->import
                ->importRows()
                ->where('status', ImportRow::STATUS_VALID)
                ->get()
                ->filter(fn (ImportRow $candidate) => $this->structuredGroupKey($candidate->raw_payload ?? []) === $groupKey)
                ->values();

            $parts = $groupRows->map(function (ImportRow $groupRow): array {
                $groupRaw = $groupRow->raw_payload ?? [];

                return [
                    'part_label' => trim((string) ($groupRaw['part_label'] ?? '')),
                    'prompt_text' => trim((string) ($groupRaw['part_prompt'] ?? '')),
                    'max_score' => (float) ($groupRaw['part_marks'] ?? 0),
                    'sample_answer' => $this->normalizeNullableString((string) ($groupRaw['part_sample_answer'] ?? '')),
                    'marking_notes' => $this->normalizeNullableString((string) ($groupRaw['part_marking_notes'] ?? '')),
                ];
            })->all();

            $payload['structured_parts'] = $parts;
            $payload['marks'] = array_sum(array_map(fn ($part) => (float) ($part['max_score'] ?? 0), $parts));
        }

        return $payload;
    }

    private function structuredGroupKey(array $raw): string
    {
        $customKey = trim((string) ($raw['question_group_key'] ?? ''));

        if ($customKey !== '') {
            return Str::lower($customKey);
        }

        return Str::lower(trim((string) ($raw['subject'] ?? '')))
            .'|'.Str::lower(trim((string) ($raw['topic'] ?? '')))
            .'|'.Str::lower(trim((string) ($raw['type'] ?? '')))
            .'|'.Str::lower(trim((string) ($raw['question_text'] ?? '')));
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
