<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkQuestionActionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('update.is_published')) {
            $this->merge([
                'update' => array_merge($this->input('update', []), [
                    'is_published' => filter_var($this->input('update.is_published'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                ]),
            ]);
        }
    }

    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('questions', 'id')],
            'action' => ['required', 'in:delete,update'],
            'delete_confirmation' => ['required_if:action,delete', 'accepted'],
            'update.subject_id' => ['nullable', 'integer', Rule::exists('subjects', 'id')],
            'update.topic_id' => ['nullable', 'integer', Rule::exists('topics', 'id')],
            'update.difficulty' => ['nullable', Rule::in(['easy', 'medium', 'hard'])],
            'update.is_published' => ['nullable', Rule::in([true, false])],
            'update.marks' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
