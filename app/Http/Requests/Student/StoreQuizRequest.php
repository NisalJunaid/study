<?php

namespace App\Http\Requests\Student;

use App\Actions\Student\BuildQuizAction;
use App\Models\Quiz;
use App\Models\Subject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && ! $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')->where('is_active', true)],
            'topic_ids' => ['nullable', 'array'],
            'topic_ids.*' => ['integer', Rule::exists('topics', 'id')->where('is_active', true)],
            'mode' => ['required', Rule::in([Quiz::MODE_MCQ, Quiz::MODE_THEORY, Quiz::MODE_MIXED])],
            'question_count' => ['required', 'integer', 'min:1', 'max:50'],
            'difficulty' => ['nullable', Rule::in(['easy', 'medium', 'hard'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $subject = Subject::query()->active()->find($this->integer('subject_id'));

            if (! $subject) {
                return;
            }

            $topicIds = collect($this->input('topic_ids', []))->map(fn ($id) => (int) $id)->all();
            $validTopicIds = $subject->topics()
                ->active()
                ->whereIn('id', $topicIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (count($validTopicIds) !== count(array_unique($topicIds))) {
                $validator->errors()->add('topic_ids', 'Selected topics must belong to the chosen subject.');

                return;
            }

            $available = app(BuildQuizAction::class)->availableQuestionCount(
                subject: $subject,
                topicIds: $validTopicIds,
                mode: (string) $this->string('mode'),
                difficulty: $this->filled('difficulty') ? (string) $this->string('difficulty') : null
            );

            if ($this->integer('question_count') > $available) {
                $validator->errors()->add(
                    'question_count',
                    "Only {$available} question(s) are available for your current filters."
                );
            }
        });
    }
}
