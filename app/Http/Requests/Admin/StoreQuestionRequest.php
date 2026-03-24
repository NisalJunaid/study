<?php

namespace App\Http\Requests\Admin;

use App\Models\Question;
use App\Models\Topic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Question::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'topic_id' => [
                'nullable',
                'integer',
                Rule::exists('topics', 'id')->where(fn ($query) => $query->where('subject_id', $this->integer('subject_id'))),
            ],
            'type' => ['required', Rule::in([Question::TYPE_MCQ, Question::TYPE_THEORY])],
            'question_text' => ['required', 'string'],
            'question_image' => ['nullable', 'image', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
            'explanation' => ['nullable', 'string'],
            'marks' => ['required', 'numeric', 'min:0'],
            'difficulty' => ['nullable', Rule::in(['easy', 'medium', 'hard'])],
            'is_published' => ['required', 'boolean'],

            'options' => ['required_if:type,mcq', 'array', 'min:2'],
            'options.*.option_key' => ['required_if:type,mcq', 'string', 'max:5', 'distinct:strict'],
            'options.*.option_text' => ['required_if:type,mcq', 'string'],
            'correct_option_key' => ['required_if:type,mcq', 'string', 'max:5'],

            'sample_answer' => ['required_if:type,theory', 'string'],
            'grading_notes' => ['nullable', 'string'],
            'keywords' => ['nullable', 'string'],
            'acceptable_phrases' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('type') === Question::TYPE_MCQ) {
                $this->validateMcq($validator);
            }

            if ($this->filled('topic_id')) {
                $topicBelongsToSubject = Topic::query()
                    ->whereKey($this->integer('topic_id'))
                    ->where('subject_id', $this->integer('subject_id'))
                    ->exists();

                if (! $topicBelongsToSubject) {
                    $validator->errors()->add('topic_id', 'The selected topic does not belong to the selected subject.');
                }
            }
        });
    }

    protected function validateMcq(Validator $validator): void
    {
        $optionKeys = collect($this->input('options', []))
            ->pluck('option_key')
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => trim($value))
            ->values();

        if ($optionKeys->unique()->count() !== $optionKeys->count()) {
            $validator->errors()->add('options', 'Each MCQ option key must be unique.');
        }

        $correctOptionKey = trim((string) $this->input('correct_option_key', ''));

        if ($correctOptionKey === '' || ! $optionKeys->contains($correctOptionKey)) {
            $validator->errors()->add('correct_option_key', 'Choose a valid correct option key from the options list.');
        }
    }
}
