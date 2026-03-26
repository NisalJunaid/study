<?php

namespace App\Http\Requests\Student;

use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveQuizAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Quiz|null $quiz */
        $quiz = $this->route('quiz');

        return $this->user() !== null && $quiz !== null && $this->user()->can('update', $quiz);
    }

    public function rules(): array
    {
        return [
            'selected_option_id' => ['nullable', 'integer'],
            'answer_text' => ['nullable', 'string'],
            'structured_answers' => ['nullable', 'array'],
            'structured_answers.*' => ['nullable', 'string'],
            'question_started_at' => ['nullable', 'date'],
            'answered_at' => ['nullable', 'date'],
            'ideal_time_seconds' => ['nullable', 'integer', 'min:1', 'max:3600'],
            'answer_duration_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'answered_on_time' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var QuizQuestion|null $quizQuestion */
            $quizQuestion = $this->route('quizQuestion');

            if (! $quizQuestion) {
                return;
            }

            $snapshot = $quizQuestion->question_snapshot ?? [];
            $type = $snapshot['type'] ?? null;

            if ($type === Question::TYPE_MCQ) {
                if ($this->filled('answer_text') || $this->filled('structured_answers')) {
                    $validator->errors()->add('answer_text', 'MCQ questions only accept option selections.');
                }

                if ($this->filled('selected_option_id')) {
                    $allowedOptionIds = collect($snapshot['options'] ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all();
                    if (! in_array((int) $this->input('selected_option_id'), $allowedOptionIds, true)) {
                        $validator->errors()->add('selected_option_id', 'Selected option is invalid for this question snapshot.');
                    }
                }

                return;
            }

            if ($type === Question::TYPE_THEORY) {
                if ($this->filled('selected_option_id') || $this->filled('structured_answers')) {
                    $validator->errors()->add('answer_text', 'Theory questions only accept free-text answers.');
                }

                return;
            }

            if ($type === Question::TYPE_STRUCTURED_RESPONSE) {
                if ($this->filled('selected_option_id') || $this->filled('answer_text')) {
                    $validator->errors()->add('structured_answers', 'Structured response questions only accept part-based answers.');
                }

                $partIds = collect($snapshot['structured_parts'] ?? [])->pluck('id')->map(fn ($id) => (string) $id)->all();
                $submittedPartIds = collect(array_keys((array) $this->input('structured_answers', [])))
                    ->map(fn ($id) => (string) $id)
                    ->all();

                $invalidPartIds = array_values(array_diff($submittedPartIds, $partIds));
                if ($invalidPartIds !== []) {
                    $validator->errors()->add('structured_answers', 'One or more structured part answers are invalid for this question.');
                }
            }
        });
    }
}
