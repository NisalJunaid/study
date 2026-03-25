<?php

namespace App\Http\Requests\Admin;

use App\Models\PlanDiscount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'subscription_plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64'],
            'type' => ['required', Rule::in([PlanDiscount::TYPE_PERCENTAGE, PlanDiscount::TYPE_FIXED])],
            'amount' => ['required', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
