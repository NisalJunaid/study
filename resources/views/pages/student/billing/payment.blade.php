@extends('layouts.student', ['heading' => 'Payment confirmation', 'subheading' => 'Review amount due, transfer to bank, and upload your slip on one clean confirmation screen.'])

@section('content')
<div class="stack-lg" id="guided-billing-payment">
    <form class="card stack-lg payment-form" method="POST" action="{{ route('student.billing.payments.store') }}" enctype="multipart/form-data" data-slip-form>
        @csrf
        <input type="hidden" name="subscription_plan_id" value="{{ $plan->id }}">

        <section class="payment-section payment-summary-card stack-sm" aria-labelledby="payment-summary-heading">
            <div class="row-between payment-heading-row">
                <h2 id="payment-summary-heading" class="h2">Amount due</h2>
                <span class="pill">Plan summary</span>
            </div>

            <div class="guided-summary payment-summary-grid">
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
                <p class="h2 mb-0 payment-total">Current due amount: {{ $breakdown['currency'] }} {{ number_format((float) $breakdown['total_due'], 2) }}</p>
            </div>
        </section>

        <section class="payment-section payment-bank-card stack-sm" aria-labelledby="payment-bank-heading">
            <div class="row-between payment-heading-row">
                <h2 id="payment-bank-heading" class="h2">Bank transfer details</h2>
                <span class="pill">Step 1</span>
            </div>

            <div class="guided-summary stack-sm payment-bank-grid">
                <p class="mb-0"><strong>Account name:</strong> {{ $setting->bank_account_name }}</p>
                <div class="row-wrap payment-copy-row">
                    <p class="mb-0"><strong>Account number:</strong> <span class="mono" data-account-number>{{ $setting->bank_account_number }}</span></p>
                    <button type="button" class="btn" data-copy-account>Copy</button>
                    <small class="muted" data-copy-feedback role="status" aria-live="polite" hidden>Copied account number.</small>
                </div>
                @if($setting->bank_name)
                    <p class="mb-0"><strong>Bank:</strong> {{ $setting->bank_name }}</p>
                @endif
                @if($setting->payment_instructions)
                    <p class="muted mb-0">{{ $setting->payment_instructions }}</p>
                @else
                    <p class="muted mb-0">Complete your transfer, then upload your payment slip below to activate temporary access while verification is pending.</p>
                @endif
            </div>
        </section>

        <section class="payment-section stack-sm" aria-labelledby="payment-upload-heading">
            <div class="row-between payment-heading-row">
                <h2 id="payment-upload-heading" class="h2">Upload payment slip</h2>
                <span class="pill">Step 2</span>
            </div>
            <p class="muted mb-0">After submission, temporary access activates immediately while verification is pending (up to 24 hours and up to 6 quizzes for today).</p>

            <label class="payment-upload-field" data-slip-preview>
                <input class="field-control payment-upload-input" type="file" name="slip" required data-slip-input accept=".jpg,.jpeg,.png,.pdf">
                <div class="payment-upload-head">
                    <p class="h3 mb-0">Bank transfer slip (JPG, PNG, PDF, max 4MB)</p>
                    <span class="btn btn-ghost">Choose file</span>
                </div>

                <p class="text-sm mb-0"><strong>Selected file:</strong> <span data-slip-filename>No file selected yet.</span></p>
                <p class="muted text-sm mb-0" data-slip-placeholder>Upload an image (JPG/PNG) to preview it here. For PDF files, we will show the filename before submission.</p>
                <p class="muted text-sm mb-0" data-slip-non-image hidden>File selected and ready to upload. PDF previews are not shown.</p>
                <img data-slip-image alt="Transfer slip preview" hidden>
            </label>
            @error('slip') <small class="field-error">{{ $message }}</small> @enderror
        </section>

        <div class="payment-actions row-wrap">
            <a class="btn" href="{{ route('student.billing.subscription') }}">Back to plans</a>
            <button type="submit" class="btn btn-primary">Submit payment proof</button>
        </div>
    </form>
</div>
@endsection
