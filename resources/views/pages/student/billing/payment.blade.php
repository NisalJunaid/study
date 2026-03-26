@extends('layouts.student', ['heading' => 'Payment confirmation', 'subheading' => 'Follow these guided steps to submit payment proof correctly.'])

@section('content')
<div class="stack-lg" id="guided-billing-payment" data-initial-step="1">
    <x-guided.stepper
        :steps="['Review pricing', 'Review bank details', 'Upload payment slip', 'Confirm submission']"
        :current="1"
        label="Payment progress"
    />

    <form class="card stack-md" method="POST" action="{{ route('student.billing.payments.store') }}" enctype="multipart/form-data" data-slip-form>
        @csrf
        <input type="hidden" name="subscription_plan_id" value="{{ $plan->id }}">

        <section class="guided-step-pane stack-sm" data-guided-step="1">
            <h2 class="h2">Step 1: Review your plan pricing</h2>
            <p class="muted mb-0">Confirm the amount due before making bank transfer.</p>
            <div class="guided-summary">
                <p class="mb-0"><strong>{{ $plan->name }}</strong> ({{ ucfirst($plan->type) }})</p>
                <p class="mb-0">Billing period: {{ \Illuminate\Support\Carbon::parse($breakdown['period_start'])->format('M d, Y') }} - {{ \Illuminate\Support\Carbon::parse($breakdown['period_end'])->format('M d, Y') }}</p>
                <p class="mb-0">Base plan rate: {{ $breakdown['currency'] }} {{ number_format((float) $breakdown['base_plan_amount'], 2) }}</p>
                @if($breakdown['is_prorated'])
                    <p class="mb-0">Prorated plan amount: {{ $breakdown['currency'] }} {{ number_format((float) $breakdown['prorated_plan_amount'], 2) }}</p>
                @endif
                @if((float) $breakdown['discount_amount'] > 0)
                    <p class="mb-0">Discount: -{{ $breakdown['currency'] }} {{ number_format((float) $breakdown['discount_amount'], 2) }}</p>
                @endif
                @if((float) $breakdown['registration_fee'] > 0)
                    <p class="mb-0">One-time registration fee: {{ $breakdown['currency'] }} {{ number_format((float) $breakdown['registration_fee'], 2) }}</p>
                @endif
                <p class="h2 mb-0">Amount due: {{ $breakdown['currency'] }} {{ number_format((float) $breakdown['total_due'], 2) }}</p>
            </div>
        </section>

        <section class="guided-step-pane stack-sm" data-guided-step="2" hidden>
            <h2 class="h2">Step 2: Review bank transfer details</h2>
            <p class="muted mb-0">Send your transfer to the exact account below.</p>
            <div class="guided-summary">
                <p class="mb-0"><strong>Account name:</strong> {{ $setting->bank_account_name }}</p>
                <p class="mb-0"><strong>Account number:</strong> {{ $setting->bank_account_number }}</p>
                @if($setting->bank_name)
                    <p class="mb-0"><strong>Bank:</strong> {{ $setting->bank_name }}</p>
                @endif
                @if($setting->payment_instructions)
                    <p class="muted mb-0">{{ $setting->payment_instructions }}</p>
                @endif
            </div>
        </section>

        <section class="guided-step-pane stack-sm" data-guided-step="3" hidden>
            <h2 class="h2">Step 3: Upload your payment slip</h2>
            <p class="muted">After submission, you receive temporary access for up to 6 quizzes today while verification is pending for up to 24 hours.</p>
            <label class="field">
                <span>Bank transfer slip (JPG, PNG, PDF, max 4MB)</span>
                <input class="field-control" type="file" name="slip" required data-slip-input>
                @error('slip') <small class="field-error">{{ $message }}</small> @enderror
            </label>

            <div class="slip-preview" data-slip-preview hidden>
                <p class="mb-0 text-sm"><strong>Preview:</strong> <span data-slip-filename></span></p>
                <img data-slip-image alt="Transfer slip preview" hidden>
                <p class="muted text-sm" data-slip-non-image hidden>File ready to upload.</p>
            </div>

            <label class="field">
                <span>Paid at (optional)</span>
                <input class="field-control" type="datetime-local" name="paid_at" value="{{ old('paid_at') }}">
                @error('paid_at') <small class="field-error">{{ $message }}</small> @enderror
            </label>
            <small class="field-error" data-step-error="3" hidden></small>
        </section>

        <section class="guided-step-pane stack-sm" data-guided-step="4" hidden>
            <h2 class="h2">Step 4: Confirm submission</h2>
            <p class="muted mb-0">Check these details, then submit payment proof.</p>
            <div class="guided-summary" data-payment-summary>
                <p class="mb-0"><strong>Plan:</strong> {{ $plan->name }} ({{ ucfirst($plan->type) }})</p>
                <p class="mb-0"><strong>Amount due:</strong> {{ $breakdown['currency'] }} {{ number_format((float) $breakdown['total_due'], 2) }}</p>
                <p class="mb-0"><strong>Destination account:</strong> {{ $setting->bank_account_number }}</p>
                <p class="mb-0"><strong>Slip:</strong> <span data-summary-slip-name>No file selected</span></p>
            </div>
            <p class="muted text-sm mb-0">What happens next: your payment enters verification, and temporary access activates immediately once submitted.</p>
        </section>

        <div class="row-wrap" style="justify-content:space-between;">
            <a class="btn" href="{{ route('student.billing.subscription') }}">Back to plans</a>
            <div class="row-wrap">
                <button type="button" class="btn" data-guided-prev>Back step</button>
                <button type="button" class="btn btn-primary" data-guided-next>Next</button>
                <button type="submit" class="btn btn-primary">Submit payment proof</button>
            </div>
        </div>
    </form>
</div>
@endsection
