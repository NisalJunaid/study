<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class WipeCurriculumRequest extends FormRequest
{
    private const PHRASES = [
        'subjects' => 'WIPE SUBJECTS',
        'topics' => 'WIPE TOPICS',
        'questions' => 'WIPE QUESTIONS',
        'answers' => 'WIPE ANSWERS',
        'all' => 'WIPE ALL CONTENT',
    ];

    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'scope' => ['required', 'in:subjects,topics,questions,answers,all'],
            'confirmation_text' => ['required', 'string', 'max:64'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $scope = (string) $this->input('scope');
            $expected = self::PHRASES[$scope] ?? null;

            if ($expected === null) {
                return;
            }

            if (trim((string) $this->input('confirmation_text')) !== $expected) {
                $validator->errors()->add('confirmation_text', "Type exactly \"{$expected}\" to confirm.");
            }
        });
    }

    public static function phraseFor(string $scope): ?string
    {
        return self::PHRASES[$scope] ?? null;
    }

    public static function phrases(): array
    {
        return self::PHRASES;
    }
}
