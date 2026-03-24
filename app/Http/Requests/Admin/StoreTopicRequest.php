<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Topic::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $name = (string) $this->input('name', '');
        $slug = (string) $this->input('slug', '');

        if ($slug === '' && $name !== '') {
            $this->merge(['slug' => Str::slug($name)]);
        }
    }

    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('topics', 'name')->where(fn ($query) => $query->where('subject_id', $this->integer('subject_id'))),
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('topics', 'slug')->where(fn ($query) => $query->where('subject_id', $this->integer('subject_id'))),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
