<?php

namespace App\Http\Requests\Admin;

use App\Models\Import;
use Illuminate\Foundation\Http\FormRequest;

class StoreQuestionImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Import::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'allow_create_subjects' => ['nullable', 'boolean'],
            'allow_create_topics' => ['nullable', 'boolean'],
        ];
    }
}
