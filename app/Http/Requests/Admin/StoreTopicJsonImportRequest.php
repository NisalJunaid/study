<?php

namespace App\Http\Requests\Admin;

use App\Models\Import;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreTopicJsonImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Import::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'topic_import_file' => ['required', 'file', 'mimes:json,txt', 'max:5120'],
        ];
    }

    public function importFile(): UploadedFile
    {
        return $this->file('topic_import_file');
    }
}
