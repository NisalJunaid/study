<?php

namespace App\Http\Requests\Admin;

use App\Models\Import;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreSubjectTopicJsonImportRequest extends FormRequest
{
    protected function getRedirectUrl(): string
    {
        return route('admin.imports.index');
    }

    public function authorize(): bool
    {
        return $this->user()?->can('create', Import::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'subject_topic_import_file' => ['required', 'file', 'mimes:json,txt', 'max:5120'],
        ];
    }

    public function importFile(): UploadedFile
    {
        return $this->file('subject_topic_import_file');
    }
}
