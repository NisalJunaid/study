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

        $preparedRows = [];
        $errors = [];
        $seenSlugs = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $errors["subjects.".($index + 1)][] = 'Each subject item must be an object.';
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
                $errors["subjects.".($index + 1)] = $validator->errors()->all();
                continue;
            }

            $slugKey = Str::lower($normalized['slug']);
            if (isset($seenSlugs[$slugKey])) {
                $errors["subjects.".($index + 1)][] = 'Duplicate slug in uploaded payload.';
                continue;
            }
            $seenSlugs[$slugKey] = true;

            $preparedRows[] = $normalized;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use (&$created, &$updated, $preparedRows, &$errors): void {
            foreach ($preparedRows as $index => $row) {
                $subject = Subject::withTrashed()->where('slug', $row['slug'])->first();

                $nameConflictExists = Subject::withTrashed()
                    ->where('name', $row['name'])
                    ->when($subject, fn ($query) => $query->where('id', '!=', $subject->id))
                    ->exists();

                if ($nameConflictExists) {
                    $errors["subjects.".($index + 1)][] = "Subject name '{$row['name']}' already exists.";
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

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        });

        return ['created' => $created, 'updated' => $updated, 'total' => count($preparedRows)];
    }

    public function importTopics(UploadedFile $file): array
    {
        $rows = $this->decodePayload($file, 'topics');

        if ($rows === []) {
            throw ValidationException::withMessages([
                'topic_import_file' => ['The topics JSON must contain at least one topic record.'],
            ]);
        }

        $subjectsBySlug = Subject::query()->get(['id', 'slug'])->keyBy(fn (Subject $subject) => Str::lower($subject->slug));

        $preparedRows = [];
        $errors = [];
        $seenKeys = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                $errors["topics.".($index + 1)][] = 'Each topic item must be an object.';
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
                $errors["topics.".($index + 1)] = $validator->errors()->all();
                continue;
            }

            $subject = $subjectsBySlug->get(Str::lower($normalized['subject_slug']));

            if (! $subject) {
                $errors["topics.".($index + 1)][] = "Subject slug '{$normalized['subject_slug']}' could not be found.";
                continue;
            }

            $duplicateKey = $subject->id.'::'.Str::lower($normalized['slug']);
            if (isset($seenKeys[$duplicateKey])) {
                $errors["topics.".($index + 1)][] = 'Duplicate topic slug for the same subject in uploaded payload.';
                continue;
            }

            $seenKeys[$duplicateKey] = true;
            $normalized['subject_id'] = $subject->id;

            $preparedRows[] = $normalized;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use (&$created, &$updated, $preparedRows, &$errors): void {
            foreach ($preparedRows as $index => $row) {
                $topic = Topic::withTrashed()
                    ->where('subject_id', $row['subject_id'])
                    ->where('slug', $row['slug'])
                    ->first();

                $nameConflictExists = Topic::withTrashed()
                    ->where('subject_id', $row['subject_id'])
                    ->where('name', $row['name'])
                    ->when($topic, fn ($query) => $query->where('id', '!=', $topic->id))
                    ->exists();

                if ($nameConflictExists) {
                    $errors["topics.".($index + 1)][] = "Topic name '{$row['name']}' already exists for this subject.";
                    continue;
                }

                $payload = Arr::except($row, ['subject_slug']);

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

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        });

        return ['created' => $created, 'updated' => $updated, 'total' => count($preparedRows)];
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

    public function subjectSampleJsonString(): string
    {
        return json_encode($this->subjectSamplePayload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function topicSampleJsonString(): string
    {
        return json_encode($this->topicSamplePayload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function decodePayload(UploadedFile $file, string $listKey): array
    {
        try {
            $contents = $file->getContent();
            $decoded = json_decode($contents ?: '', true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $field = $listKey === 'topics' ? 'topic_import_file' : 'subject_import_file';

            throw ValidationException::withMessages([
                $field => ['Malformed JSON file: '.$exception->getMessage()],
            ]);
        }

        if (is_array($decoded) && array_is_list($decoded)) {
            return $decoded;
        }

        if (is_array($decoded) && isset($decoded[$listKey]) && is_array($decoded[$listKey]) && array_is_list($decoded[$listKey])) {
            return $decoded[$listKey];
        }

        $field = $listKey === 'topics' ? 'topic_import_file' : 'subject_import_file';

        throw ValidationException::withMessages([
            $field => ["Unsupported JSON structure. Use an array of objects or an object with a '{$listKey}' array."],
        ]);
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
