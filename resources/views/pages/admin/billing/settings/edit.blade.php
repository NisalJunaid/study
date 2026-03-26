@extends('layouts.admin', ['heading' => 'Billing Payment Settings'])

@section('content')
<x-admin.flash />
<div class="card" style="max-width: 860px;">
    <form method="POST" action="{{ route('admin.billing.settings.update') }}" class="stack-md">
        @csrf
        @method('PUT')

        <label class="field">
            <span>Account name</span>
            <input class="field-control" name="bank_account_name" value="{{ old('bank_account_name', $setting->bank_account_name) }}" required>
            @error('bank_account_name') <small class="field-error">{{ $message }}</small> @enderror
        </label>

        <label class="field">
            <span>Account number</span>
            <input class="field-control" name="bank_account_number" value="{{ old('bank_account_number', $setting->bank_account_number) }}" required>
            @error('bank_account_number') <small class="field-error">{{ $message }}</small> @enderror
        </label>

        <label class="field">
            <span>Bank name (optional)</span>
            <input class="field-control" name="bank_name" value="{{ old('bank_name', $setting->bank_name) }}">
            @error('bank_name') <small class="field-error">{{ $message }}</small> @enderror
        </label>

        <label class="field">
            <span>Currency display</span>
            <input class="field-control" name="currency" value="{{ old('currency', $setting->currency) }}" required>
            @error('currency') <small class="field-error">{{ $message }}</small> @enderror
        </label>

        <label class="field">
            <span>One-time registration fee</span>
            <input class="field-control" name="registration_fee" type="number" min="0" step="0.01" value="{{ old('registration_fee', $setting->registration_fee) }}" required>
            @error('registration_fee') <small class="field-error">{{ $message }}</small> @enderror
        </label>

        <label class="field">
            <span>Daily AI credits per student</span>
            <input class="field-control" name="daily_ai_credits" type="number" min="0" step="1" value="{{ old('daily_ai_credits', $setting->daily_ai_credits) }}" required>
            @error('daily_ai_credits') <small class="field-error">{{ $message }}</small> @enderror
        </label>

        <label class="field">
            <span>Mixed quiz AI question weight (%)</span>
            <input class="field-control" name="mixed_quiz_ai_weight_percentage" type="number" min="0" max="100" step="1" value="{{ old('mixed_quiz_ai_weight_percentage', $setting->mixed_quiz_ai_weight_percentage) }}" required>
            <small class="muted text-xs">Controls the target share of AI-marked question types in mixed quizzes.</small>
            @error('mixed_quiz_ai_weight_percentage') <small class="field-error">{{ $message }}</small> @enderror
        </label>

        <label class="field">
            <span>Payment instructions (optional)</span>
            <textarea class="field-control" name="payment_instructions" rows="4">{{ old('payment_instructions', $setting->payment_instructions) }}</textarea>
            @error('payment_instructions') <small class="field-error">{{ $message }}</small> @enderror
        </label>

        <button class="btn btn-primary" type="submit">Save settings</button>
    </form>
</div>
@endsection
