<?php

namespace App\Http\Requests\Admin;

use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $planId = (int) $this->route('plan')->id;

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('subscription_plans', 'code')->ignore($planId)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in([SubscriptionPlan::TYPE_MONTHLY, SubscriptionPlan::TYPE_ANNUAL])],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:8'],
            'billing_cycle_days' => ['nullable', 'integer', 'min:1', 'max:366'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
