<?php

namespace App\Actions\Admin;

use App\Models\Question;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UpsertQuestionAction
{
    public function execute(array $payload, User $user, ?Question $question = null, ?UploadedFile $image = null): Question
    {
        return DB::transaction(function () use ($payload, $user, $question, $image): Question {
            $question = $question ?? new Question();
            $isNew = ! $question->exists;

            $questionData = Arr::only($payload, [
                'subject_id',
                'topic_id',
                'type',
                'question_text',
                'difficulty',
                'explanation',
                'marks',
                'is_published',
            ]);

            if ($image) {
                if ($question->question_image_path) {
                    Storage::disk('public')->delete($question->question_image_path);
                }

                $questionData['question_image_path'] = $image->store('questions', 'public');
            }

            if (! empty($payload['remove_image']) && $question->question_image_path) {
                Storage::disk('public')->delete($question->question_image_path);
                $questionData['question_image_path'] = null;
            }

            if ($isNew) {
                $questionData['created_by'] = $user->id;
            }

            $questionData['updated_by'] = $user->id;

            $question->fill($questionData);
            $question->save();

            if ($question->type === Question::TYPE_MCQ) {
                $question->theoryMeta()->delete();
                $question->structuredParts()->delete();
                $question->mcqOptions()->delete();

                $correctOptionKey = (string) ($payload['correct_option_key'] ?? '');
                foreach ($payload['options'] as $index => $option) {
                    $question->mcqOptions()->create([
                        'option_key' => $option['option_key'],
                        'option_text' => $option['option_text'],
                        'is_correct' => $option['option_key'] === $correctOptionKey,
                        'sort_order' => $index,
                    ]);
                }
            }

            if ($question->type === Question::TYPE_THEORY) {
                $question->mcqOptions()->delete();
                $question->structuredParts()->delete();

                $question->theoryMeta()->updateOrCreate(
                    ['question_id' => $question->id],
                    [
                        'sample_answer' => $payload['sample_answer'],
                        'grading_notes' => $payload['grading_notes'] ?: null,
                        'keywords' => $this->normalizePipeList($payload['keywords'] ?? ''),
                        'acceptable_phrases' => $this->normalizePipeList($payload['acceptable_phrases'] ?? ''),
                        'max_score' => $question->marks,
                    ]
                );
            }

            if ($question->type === Question::TYPE_STRUCTURED_RESPONSE) {
                $question->mcqOptions()->delete();
                $question->theoryMeta()->delete();
                $question->structuredParts()->delete();

                foreach ($payload['structured_parts'] as $index => $part) {
                    $question->structuredParts()->create([
                        'part_label' => trim((string) $part['part_label']),
                        'prompt_text' => trim((string) $part['prompt_text']),
                        'max_score' => (float) $part['max_score'],
                        'sample_answer' => $this->normalizeNullableString($part['sample_answer'] ?? null),
                        'marking_notes' => $this->normalizeNullableString($part['marking_notes'] ?? null),
                        'sort_order' => $index,
                    ]);
                }
            }

            return $question;
        });
    }

    private function normalizePipeList(string $rawValue): ?array
    {
        $values = collect(preg_split('/\r\n|\r|\n|\|/', $rawValue) ?: [])
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->values()
            ->all();

        return $values === [] ? null : $values;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }
}
