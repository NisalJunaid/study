<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'bank_account_name' => ['required', 'string', 'max:255'],
            'bank_account_number' => ['required', 'string', 'max:64'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'max:8'],
            'registration_fee' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'daily_ai_credits' => ['required', 'integer', 'min:0', 'max:5000'],
            'mixed_quiz_ai_weight_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'payment_instructions' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
