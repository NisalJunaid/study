<?php

namespace App\Services\Import;

use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;

class CurriculumJsonImportService
{
    public function importSubjects(UploadedFile $file): array
    {
        $rows = $this->decodePayload($file, 'subjects');

        if ($rows === []) {
            throw ValidationException::withMessages([
                'subject_import_file' => ['The subjects JSON must contain at least one subject record.'],
            ]);
        }

        $errors = [];
        $preparedRows = $this->prepareSubjectRows($rows, $errors, 'subjects');

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($preparedRows): array {
            return $this->syncSubjects($preparedRows);
        });
    }

    public function importTopics(UploadedFile $file): array
    {
        $rows = $this->decodePayload($file, 'topics');

        if ($rows === []) {
            throw ValidationException::withMessages([
                'topic_import_file' => ['The topics JSON must contain at least one topic record.'],
            ]);
        }

        $errors = [];
        $preparedRows = $this->prepareTopicRows($rows, $errors, 'topics');

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($preparedRows): array {
            return [
                'topics' => $this->syncTopics($preparedRows),
            ];
        });
    }

    public function importSubjectsAndTopics(UploadedFile $file): array
    {
        $decoded = $this->decodeRootPayload($file, 'subject_topic_import_file');

        if (! array_key_exists('subjects', $decoded) && ! array_key_exists('topics', $decoded)) {
            throw ValidationException::withMessages([
                'subject_topic_import_file' => ["The JSON object must include at least one of 'subjects' or 'topics' arrays."],
            ]);
        }

        $subjectRows = $this->extractListFromObject($decoded, 'subjects', 'subject_topic_import_file');
        $topicRows = $this->extractListFromObject($decoded, 'topics', 'subject_topic_import_file');

        if ($subjectRows === [] && $topicRows === []) {
            throw ValidationException::withMessages([
                'subject_topic_import_file' => ['The import file must include at least one subject or topic record.'],
            ]);
        }

        $errors = [];
        $preparedSubjects = $this->prepareSubjectRows($subjectRows, $errors, 'subjects');
        $preparedTopics = $this->prepareTopicRows($topicRows, $errors, 'topics');

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($preparedSubjects, $preparedTopics): array {
            $subjectResult = [
                'levels' => $this->emptySummary(0),
                'subjects' => $this->emptySummary(0),
            ];

            if ($preparedSubjects !== []) {
                $subjectResult = $this->syncSubjects($preparedSubjects);
            }

            $topicResult = $this->emptySummary(count($preparedTopics));
            if ($preparedTopics !== []) {
                $topicResult = $this->syncTopics($preparedTopics);
            }

            return [
                'levels' => $subjectResult['levels'],
                'subjects' => $subjectResult['subjects'],
                'topics' => $topicResult,
            ];
        });
    }

    public function subjectSamplePayload(): array
    {
        return [[
            'name' => 'Biology',
            'slug' => 'biology',
            'level' => Subject::LEVEL_O,
            'description' => 'Core O Level biology syllabus.',
            'color' => '#22c55e',
            'icon' => 'flask',
            'is_active' => true,
            'sort_order' => 1,
        ]];
    }

    public function topicSamplePayload(): array
    {
        return [[
            'subject_slug' => 'biology',
            'name' => 'Cell Structure',
            'slug' => 'cell-structure',
            'description' => 'Plant and animal cell organelles.',
            'is_active' => true,
            'sort_order' => 1,
        ]];
    }

    public function combinedSubjectTopicSamplePayload(): array
    {
        return [
            'subjects' => [
                [
                    'name' => 'Biology',
                    'slug' => 'biology',
                    'level' => Subject::LEVEL_O,
                    'description' => 'Core O Level biology syllabus.',
                    'color' => '#22c55e',
                    'icon' => 'flask',
                    'is_active' => true,
                    'sort_order' => 1,
                ],
                [
                    'name' => 'Chemistry',
                    'slug' => 'chemistry',
                    'level' => Subject::LEVEL_O,
                    'description' => 'Core O Level chemistry syllabus.',
                    'color' => '#16a34a',
                    'icon' => 'beaker',
                    'is_active' => true,
                    'sort_order' => 2,
                ],
            ],
            'topics' => [
                [
                    'subject_slug' => 'biology',
                    'name' => 'Cell Structure',
                    'slug' => 'cell-structure',
                    'description' => 'Plant and animal cell organelles.',
                    'is_active' => true,
                    'sort_order' => 1,
                ],
                [
                    'subject_slug' => 'biology',
                    'name' => 'Genetics',
                    'slug' => 'genetics',
                    'description' => 'Inheritance and DNA basics.',
                    'is_active' => true,
                    'sort_order' => 2,
                ],
                [
                    'subject_slug' => 'chemistry',
                    'name' => 'Atomic Structure',
                    'slug' => 'atomic-structure',
                    'description' => 'Subatomic particles and electron shells.',
                    'is_active' => true,
                    'sort_order' => 1,
                ],
            ],
        ];
    }

    public function subjectSampleJsonString(): string
    {
        return json_encode($this->subjectSamplePayload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function topicSampleJsonString(): string
    {
        return json_encode($this->topicSamplePayload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function combinedSubjectTopicSampleJsonString(): string
    {
        return json_encode($this->combinedSubjectTopicSamplePayload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function decodePayload(UploadedFile $file, string $listKey): array
    {
        $decoded = $this->decodeRootPayload($file, $listKey === 'topics' ? 'topic_import_file' : 'subject_import_file');

        if (array_is_list($decoded)) {
            return $decoded;
        }

        if (isset($decoded[$listKey]) && is_array($decoded[$listKey]) && array_is_list($decoded[$listKey])) {
            return $decoded[$listKey];
        }

        $field = $listKey === 'topics' ? 'topic_import_file' : 'subject_import_file';

        throw ValidationException::withMessages([
            $field => ["Unsupported JSON structure. Use an array of objects or an object with a '{$listKey}' array."],
        ]);
    }

    private function decodeRootPayload(UploadedFile $file, string $errorField): array
    {
        try {
            $contents = $file->getContent();
            $decoded = json_decode($contents ?: '', true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw ValidationException::withMessages([
                $errorField => ['Malformed JSON file: '.$exception->getMessage()],
            ]);
        }

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                $errorField => ['Unsupported JSON structure. Expected a JSON array or object.'],
            ]);
        }

        return $decoded;
    }

    private function extractListFromObject(array $payload, string $key, string $errorField): array
    {
        if (! array_key_exists($key, $payload)) {
            return [];
        }

        if (! is_array($payload[$key]) || ! array_is_list($payload[$key])) {
            throw ValidationException::withMessages([
                $errorField => ["The '{$key}' key must be an array of objects."],
            ]);
        }

        return $payload[$key];
    }

    private function prepareSubjectRows(array $rows, array &$errors, string $errorPrefix): array
    {
        $preparedRows = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $errors[$errorPrefix.'.'.($index + 1)][] = 'Each subject item must be an object.';
                continue;
            }

            $normalized = [
                'name' => trim((string) Arr::get($row, 'name', '')),
                'slug' => trim((string) Arr::get($row, 'slug', '')),
                'level' => trim((string) Arr::get($row, 'level', '')),
                'description' => $this->nullableString(Arr::get($row, 'description')),
                'color' => $this->nullableString(Arr::get($row, 'color')),
                'icon' => $this->nullableString(Arr::get($row, 'icon')),
                'is_active' => Arr::has($row, 'is_active') ? Arr::get($row, 'is_active') : true,
                'sort_order' => Arr::has($row, 'sort_order') ? Arr::get($row, 'sort_order') : 0,
            ];

            $validator = Validator::make($normalized, [
                'name' => ['required', 'string', 'max:255'],
                'slug' => ['required', 'string', 'max:255', 'alpha_dash'],
                'level' => ['required', 'string', 'in:'.implode(',', Subject::levels())],
                'description' => ['nullable', 'string'],
                'color' => ['nullable', 'regex:/^#?([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/'],
                'icon' => ['nullable', 'string', 'max:100'],
                'is_active' => ['required', 'boolean'],
                'sort_order' => ['required', 'integer', 'min:0'],
            ]);

            if ($validator->fails()) {
                $errors[$errorPrefix.'.'.($index + 1)] = $validator->errors()->all();
                continue;
            }

            $preparedRows[] = $normalized;
        }

        return $preparedRows;
    }

    private function prepareTopicRows(array $rows, array &$errors, string $errorPrefix): array
    {
        $preparedRows = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $errors[$errorPrefix.'.'.($index + 1)][] = 'Each topic item must be an object.';
                continue;
            }

            $normalized = [
                'subject_slug' => trim((string) Arr::get($row, 'subject_slug', '')),
                'name' => trim((string) Arr::get($row, 'name', '')),
                'slug' => trim((string) Arr::get($row, 'slug', '')),
                'description' => $this->nullableString(Arr::get($row, 'description')),
                'is_active' => Arr::has($row, 'is_active') ? Arr::get($row, 'is_active') : true,
                'sort_order' => Arr::has($row, 'sort_order') ? Arr::get($row, 'sort_order') : 0,
            ];

            $validator = Validator::make($normalized, [
                'subject_slug' => ['required', 'string', 'max:255', 'alpha_dash'],
                'name' => ['required', 'string', 'max:255'],
                'slug' => ['required', 'string', 'max:255', 'alpha_dash'],
                'description' => ['nullable', 'string'],
                'is_active' => ['required', 'boolean'],
                'sort_order' => ['required', 'integer', 'min:0'],
            ]);

            if ($validator->fails()) {
                $errors[$errorPrefix.'.'.($index + 1)] = $validator->errors()->all();
                continue;
            }

            $preparedRows[] = $normalized;
        }

        return $preparedRows;
    }

    private function syncSubjects(array $preparedRows): array
    {
        $levelsTotal = count(array_unique(array_map(fn (array $row): string => Str::lower($row['level']), $preparedRows)));
        $subjectSummary = $this->emptySummary(count($preparedRows));

        $levelsPresent = Subject::query()
            ->select('level')
            ->whereIn('level', array_values(array_unique(array_map(fn (array $row): string => $row['level'], $preparedRows))))
            ->pluck('level')
            ->map(fn (string $level): string => Str::lower($level))
            ->all();

        $levelsCreated = max(0, $levelsTotal - count(array_unique($levelsPresent)));
        $levelSummary = [
            'created' => $levelsCreated,
            'skipped' => max(0, $levelsTotal - $levelsCreated),
            'failed' => 0,
            'total' => $levelsTotal,
        ];

        $seenSubjectSlugs = [];

        foreach ($preparedRows as $row) {
            $slugKey = Str::lower($row['slug']);
            if (isset($seenSubjectSlugs[$slugKey])) {
                $subjectSummary['skipped']++;
                continue;
            }
            $seenSubjectSlugs[$slugKey] = true;

            $existingBySlug = Subject::withTrashed()->whereRaw('LOWER(slug) = ?', [$slugKey])->first();
            if ($existingBySlug) {
                if ($existingBySlug->trashed()) {
                    $existingBySlug->restore();
                }
                $subjectSummary['skipped']++;
                continue;
            }

            $nameConflictExists = Subject::withTrashed()->where('name', $row['name'])->exists();
            if ($nameConflictExists) {
                $subjectSummary['failed']++;
                continue;
            }

            $payload = $row;
            if ($payload['color'] !== null) {
                $payload['color'] = Subject::normalizeColor($payload['color']);
            }

            Subject::create($payload);
            $subjectSummary['created']++;
        }

        return [
            'levels' => $levelSummary,
            'subjects' => $subjectSummary,
        ];
    }

    private function syncTopics(array $preparedRows): array
    {
        $summary = $this->emptySummary(count($preparedRows));
        $seenKeys = [];

        foreach ($preparedRows as $row) {
            $key = Str::lower($row['subject_slug']).'::'.Str::lower($row['slug']);
            if (isset($seenKeys[$key])) {
                $summary['skipped']++;
                continue;
            }
            $seenKeys[$key] = true;

            $subject = Subject::query()->whereRaw('LOWER(slug) = ?', [Str::lower($row['subject_slug'])])->first();
            if (! $subject) {
                $summary['failed']++;
                continue;
            }

            $topic = Topic::withTrashed()
                ->where('subject_id', $subject->id)
                ->whereRaw('LOWER(slug) = ?', [Str::lower($row['slug'])])
                ->first();

            if ($topic) {
                if ($topic->trashed()) {
                    $topic->restore();
                }
                $summary['skipped']++;
                continue;
            }

            $nameConflictExists = Topic::withTrashed()
                ->where('subject_id', $subject->id)
                ->where('name', $row['name'])
                ->exists();

            if ($nameConflictExists) {
                $summary['failed']++;
                continue;
            }

            $payload = Arr::except($row, ['subject_slug']);
            $payload['subject_id'] = $subject->id;

            Topic::create($payload);
            $summary['created']++;
        }

        return $summary;
    }

    private function emptySummary(int $total): array
    {
        return [
            'created' => 0,
            'skipped' => 0,
            'failed' => 0,
            'total' => $total,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
