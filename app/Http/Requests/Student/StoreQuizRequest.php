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
    protected function prepareForValidation(): void
    {
        $subjectIds = $this->input('subject_ids', []);

        $this->merge([
            'levels' => collect($this->input('levels', []))->map(fn ($value) => (string) $value)->unique()->values()->all(),
            'question_count' => $this->input('question_count', 50),
            'multi_subject_mode' => $this->boolean('multi_subject_mode'),
            'subject_ids' => is_array($subjectIds) ? array_values(array_unique(array_map('intval', $subjectIds))) : [],
            'topic_ids' => collect($this->input('topic_ids', []))->map(fn ($id) => (int) $id)->unique()->values()->all(),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user() !== null && ! $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'preset' => ['nullable', 'string'],
            'levels' => ['required', 'array', 'min:1'],
            'levels.*' => [Rule::in(Subject::levels())],
            'multi_subject_mode' => ['required', 'boolean'],
            'subject_id' => ['nullable', 'integer', Rule::exists('subjects', 'id')->where('is_active', true)],
            'subject_ids' => ['nullable', 'array'],
            'subject_ids.*' => ['integer', Rule::exists('subjects', 'id')->where('is_active', true)],
            'topic_ids' => ['nullable', 'array'],
            'topic_ids.*' => ['integer', Rule::exists('topics', 'id')->where('is_active', true)],
            'mode' => ['required', Rule::in([Quiz::MODE_MCQ, Quiz::MODE_THEORY, Quiz::MODE_MIXED])],
            'question_count' => ['required', 'integer', 'min:1', 'max:100'],
            'difficulty' => ['nullable', Rule::in(['easy', 'medium', 'hard'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $multiMode = $this->boolean('multi_subject_mode');
            $subjectIds = $this->selectedSubjectIds();

            if ($subjectIds === []) {
                $validator->errors()->add('subject_id', 'Select at least one subject to continue.');

                return;
            }

            if (! $multiMode && count($subjectIds) !== 1) {
                $validator->errors()->add('subject_id', 'Single-subject mode only allows one subject.');

                return;
            }

            $subjects = Subject::query()
                ->active()
                ->whereIn('id', $subjectIds)
                ->with(['topics' => fn ($query) => $query->active()->select('topics.id', 'topics.subject_id')])
                ->get(['id', 'level']);

            if ($subjects->count() !== count($subjectIds)) {
                $validator->errors()->add('subject_id', 'One or more selected subjects are invalid.');

                return;
            }

            $levels = collect($this->input('levels', []))
                ->map(fn ($value) => (string) $value)
                ->filter(fn (string $value) => in_array($value, Subject::levels(), true))
                ->unique()
                ->values();

            if ($levels->isEmpty()) {
                $validator->errors()->add('levels', 'Select at least one level.');

                return;
            }

            if ($subjects->contains(fn (Subject $subject) => ! $levels->contains($subject->level))) {
                $validator->errors()->add('subject_id', 'Selected subjects must belong to the chosen level selection.');

                return;
            }

            $validTopicIds = $subjects
                ->flatMap(fn (Subject $subject) => $subject->topics->pluck('id'))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            $topicIds = collect($this->input('topic_ids', []))->map(fn ($id) => (int) $id)->all();
            $invalidTopicIds = array_values(array_diff($topicIds, $validTopicIds));
            if ($invalidTopicIds !== []) {
                $validator->errors()->add('topic_ids', 'Selected topics must belong to selected subject(s).');

                return;
            }

            $available = app(BuildQuizAction::class)->availableQuestionCount(
                subjectIds: $subjectIds,
                topicIds: $topicIds,
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

    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated();

        $subjectIds = $this->selectedSubjectIds();

        $validated['subject_ids'] = $subjectIds;
        $validated['subject_id'] = count($subjectIds) === 1 ? $subjectIds[0] : null;

        return $validated;
    }

    private function selectedSubjectIds(): array
    {
        if ($this->boolean('multi_subject_mode')) {
            return collect($this->input('subject_ids', []))
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();
        }

        $subjectId = (int) $this->input('subject_id');

        return $subjectId > 0 ? [$subjectId] : [];
    }
}
