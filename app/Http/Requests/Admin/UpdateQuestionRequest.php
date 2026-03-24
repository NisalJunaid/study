<?php

namespace App\Http\Requests\Admin;

use App\Models\Question;

class UpdateQuestionRequest extends StoreQuestionRequest
{
    public function authorize(): bool
    {
        $question = $this->route('question');

        return $question ? ($this->user()?->can('update', $question) ?? false) : false;
    }
}
