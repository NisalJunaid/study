<?php

namespace App\Http\Requests\Admin;

use App\Models\Import;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreSubjectJsonImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Import::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'subject_import_file' => ['required', 'file', 'mimes:json,txt', 'max:5120'],
        ];
    }

    public function importFile(): UploadedFile
    {
        return $this->file('subject_import_file');
    }
}
