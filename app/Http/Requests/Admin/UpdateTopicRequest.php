<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        $topic = $this->route('topic');

        return $topic ? ($this->user()?->can('update', $topic) ?? false) : false;
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
        $topicId = $this->route('topic')?->id;

        return [
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('topics', 'name')
                    ->where(fn ($query) => $query->where('subject_id', $this->integer('subject_id')))
                    ->ignore($topicId),
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('topics', 'slug')
                    ->where(fn ($query) => $query->where('subject_id', $this->integer('subject_id')))
                    ->ignore($topicId),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
