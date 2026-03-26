<?php

namespace App\Http\Requests\Admin;

use App\Models\Import;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreQuestionImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Import::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'import_file' => ['required_without:csv_file', 'file', 'mimes:csv,txt,json', 'max:5120'],
            'csv_file' => ['required_without:import_file', 'file', 'mimes:csv,txt,json', 'max:5120'],
            'allow_create_subjects' => ['nullable', 'boolean'],
            'allow_create_topics' => ['nullable', 'boolean'],
        ];
    }

    public function importFile(): ?UploadedFile
    {
        return $this->file('import_file') ?? $this->file('csv_file');
    }
}
