<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkTopicActionRequest extends FormRequest
{
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
            'update.subject_id' => ['nullable', 'integer', Rule::exists('subjects', 'id')],
            'update.is_active' => ['nullable', 'boolean'],
            'update.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
