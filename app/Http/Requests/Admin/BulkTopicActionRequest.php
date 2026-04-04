<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkTopicActionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('update.is_active')) {
            $this->merge([
                'update' => array_merge($this->input('update', []), [
                    'is_active' => filter_var($this->input('update.is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
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
            'ids.*' => ['integer', Rule::exists('topics', 'id')],
            'action' => ['required', 'in:delete,update'],
            'delete_confirmation' => ['required_if:action,delete', 'accepted'],
            'update.subject_id' => ['nullable', 'integer', Rule::exists('subjects', 'id')],
            'update.is_active' => ['nullable', Rule::in([true, false])],
            'update.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
