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

        $result = ['created' => 0, 'updated' => 0, 'total' => count($preparedRows)];

        DB::transaction(function () use (&$result, $preparedRows, &$errors): void {
            $result = $this->upsertSubjects($preparedRows, $errors, 'subjects');

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        });

        return $result;
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

        $result = ['created' => 0, 'updated' => 0, 'total' => count($preparedRows)];

        DB::transaction(function () use (&$result, $preparedRows, &$errors): void {
            $result = $this->upsertTopics($preparedRows, $errors, 'topics');

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        });

        return $result;
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

        if ($preparedSubjects !== []) {
            $subjectSlugs = collect($preparedSubjects)->mapWithKeys(
                fn (array $row): array => [Str::lower($row['slug']) => true]
            )->all();
        } else {
            $subjectSlugs = [];
        }

        $preparedTopics = $this->prepareTopicRows($topicRows, $errors, 'topics', $subjectSlugs);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $subjectResult = ['created' => 0, 'updated' => 0, 'total' => count($preparedSubjects)];
        $topicResult = ['created' => 0, 'updated' => 0, 'total' => count($preparedTopics)];

        DB::transaction(function () use (&$subjectResult, &$topicResult, $preparedSubjects, $preparedTopics, &$errors): void {
            if ($preparedSubjects !== []) {
                $subjectResult = $this->upsertSubjects($preparedSubjects, $errors, 'subjects');
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            if ($preparedTopics !== []) {
                $topicResult = $this->upsertTopics($preparedTopics, $errors, 'topics');
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        });

        return [
            'subjects' => $subjectResult,
            'topics' => $topicResult,
        ];
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
        $seenSlugs = [];

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

            $slugKey = Str::lower($normalized['slug']);
            if (isset($seenSlugs[$slugKey])) {
                $errors[$errorPrefix.'.'.($index + 1)][] = 'Duplicate slug in uploaded payload.';
                continue;
            }

            $seenSlugs[$slugKey] = true;
            $preparedRows[] = $normalized;
        }

        return $preparedRows;
    }

    private function prepareTopicRows(array $rows, array &$errors, string $errorPrefix, array $allowedSubjectSlugs = []): array
    {
        $subjectsBySlug = Subject::query()->get(['id', 'slug'])->keyBy(fn (Subject $subject) => Str::lower($subject->slug));

        $preparedRows = [];
        $seenKeys = [];

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

            $subjectSlugKey = Str::lower($normalized['subject_slug']);
            $subject = $subjectsBySlug->get($subjectSlugKey);
            if (! $subject && ! isset($allowedSubjectSlugs[$subjectSlugKey])) {
                $errors[$errorPrefix.'.'.($index + 1)][] = "Subject slug '{$normalized['subject_slug']}' could not be found.";
                continue;
            }

            $duplicateKey = $subjectSlugKey.'::'.Str::lower($normalized['slug']);
            if (isset($seenKeys[$duplicateKey])) {
                $errors[$errorPrefix.'.'.($index + 1)][] = 'Duplicate topic slug for the same subject in uploaded payload.';
                continue;
            }

            $seenKeys[$duplicateKey] = true;
            $preparedRows[] = $normalized;
        }

        return $preparedRows;
    }

    private function upsertSubjects(array $preparedRows, array &$errors, string $errorPrefix): array
    {
        $created = 0;
        $updated = 0;

        foreach ($preparedRows as $index => $row) {
            $subject = Subject::withTrashed()->where('slug', $row['slug'])->first();

            $nameConflictExists = Subject::withTrashed()
                ->where('name', $row['name'])
                ->when($subject, fn ($query) => $query->where('id', '!=', $subject->id))
                ->exists();

            if ($nameConflictExists) {
                $errors[$errorPrefix.'.'.($index + 1)][] = "Subject name '{$row['name']}' already exists.";
                continue;
            }

            $payload = $row;
            if ($payload['color'] !== null) {
                $payload['color'] = Subject::normalizeColor($payload['color']);
            }

            if ($subject) {
                $subject->fill($payload);
                if ($subject->trashed()) {
                    $subject->restore();
                }
                $subject->save();
                $updated++;
                continue;
            }

            Subject::create($payload);
            $created++;
        }

        return ['created' => $created, 'updated' => $updated, 'total' => count($preparedRows)];
    }

    private function upsertTopics(array $preparedRows, array &$errors, string $errorPrefix): array
    {
        $subjectsBySlug = Subject::query()->get(['id', 'slug'])->keyBy(fn (Subject $subject) => Str::lower($subject->slug));
        $created = 0;
        $updated = 0;

        foreach ($preparedRows as $index => $row) {
            $subject = $subjectsBySlug->get(Str::lower($row['subject_slug']));

            if (! $subject) {
                $errors[$errorPrefix.'.'.($index + 1)][] = "Subject slug '{$row['subject_slug']}' could not be found.";
                continue;
            }

            $topic = Topic::withTrashed()
                ->where('subject_id', $subject->id)
                ->where('slug', $row['slug'])
                ->first();

            $nameConflictExists = Topic::withTrashed()
                ->where('subject_id', $subject->id)
                ->where('name', $row['name'])
                ->when($topic, fn ($query) => $query->where('id', '!=', $topic->id))
                ->exists();

            if ($nameConflictExists) {
                $errors[$errorPrefix.'.'.($index + 1)][] = "Topic name '{$row['name']}' already exists for this subject.";
                continue;
            }

            $payload = Arr::except($row, ['subject_slug']);
            $payload['subject_id'] = $subject->id;

            if ($topic) {
                $topic->fill($payload);
                if ($topic->trashed()) {
                    $topic->restore();
                }
                $topic->save();
                $updated++;
                continue;
            }

            Topic::create($payload);
            $created++;
        }

        return ['created' => $created, 'updated' => $updated, 'total' => count($preparedRows)];
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
