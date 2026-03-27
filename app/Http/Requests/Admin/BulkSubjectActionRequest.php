<?php

namespace App\Http\Requests\Admin;

use App\Models\Subject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkSubjectActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('subjects', 'id')],
            'action' => ['required', 'in:delete,update'],
            'update.level' => ['nullable', Rule::in(Subject::levels())],
            'update.color' => ['nullable', 'string', 'max:20'],
            'update.is_active' => ['nullable', 'boolean'],
            'update.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
