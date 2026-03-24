<?php

namespace App\Http\Requests\Student;

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

            if ($type === 'mcq') {
                if ($this->filled('answer_text')) {
                    $validator->errors()->add('answer_text', 'MCQ questions do not accept theory text answers.');
                }

                if ($this->filled('selected_option_id')) {
                    $allowedOptionIds = collect($snapshot['options'] ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all();
                    if (! in_array((int) $this->input('selected_option_id'), $allowedOptionIds, true)) {
                        $validator->errors()->add('selected_option_id', 'Selected option is invalid for this question snapshot.');
                    }
                }

                return;
            }

            if ($type === 'theory' && $this->filled('selected_option_id')) {
                $validator->errors()->add('selected_option_id', 'Theory questions do not support option selection.');
            }
        });
    }
}
