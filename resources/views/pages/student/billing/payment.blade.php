@extends('layouts.student', ['heading' => 'Payment confirmation', 'subheading' => 'Review amount due, transfer to bank, and upload your slip on this page.'])

@section('content')
<div class="stack-lg" id="guided-billing-payment">
    <form class="card stack-md" method="POST" action="{{ route('student.billing.payments.store') }}" enctype="multipart/form-data" data-slip-form>
        @csrf
        <input type="hidden" name="subscription_plan_id" value="{{ $plan->id }}">

        <section class="stack-sm">
            <h2 class="h2">Amount due</h2>
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
                <p class="h2 mb-0">Current due amount: {{ $breakdown['currency'] }} {{ number_format((float) $breakdown['total_due'], 2) }}</p>
            </div>
        </section>

        <section class="stack-sm">
            <h2 class="h2">Bank transfer details</h2>
            <div class="guided-summary stack-sm">
                <p class="mb-0"><strong>Account name:</strong> {{ $setting->bank_account_name }}</p>
                <div class="row-wrap" style="align-items:center;">
                    <p class="mb-0"><strong>Account number:</strong> <span class="mono" data-account-number>{{ $setting->bank_account_number }}</span></p>
                    <button type="button" class="btn" data-copy-account>Copy</button>
                    <small class="muted" data-copy-feedback role="status" aria-live="polite" hidden>Copied account number.</small>
                </div>
                @if($setting->bank_name)
                    <p class="mb-0"><strong>Bank:</strong> {{ $setting->bank_name }}</p>
                @endif
                @if($setting->payment_instructions)
                    <p class="muted mb-0">{{ $setting->payment_instructions }}</p>
                @endif
            </div>
        </section>

        <section class="stack-sm">
            <h2 class="h2">Upload payment slip</h2>
            <p class="muted">After submission, temporary access activates immediately while verification is pending (up to 24 hours and up to 6 quizzes for today).</p>

            <div class="slip-preview" data-slip-preview>
                <p class="mb-0 text-sm"><strong>Preview:</strong> <span data-slip-filename>No file selected yet.</span></p>
                <img data-slip-image alt="Transfer slip preview" hidden>
                <p class="muted text-sm mb-0" data-slip-non-image hidden>File selected and ready to upload.</p>
                <p class="muted text-sm mb-0" data-slip-placeholder>Upload an image (JPG/PNG) to see a preview here. PDF files will show filename only.</p>
            </div>

            <label class="field">
                <span>Bank transfer slip (JPG, PNG, PDF, max 4MB)</span>
                <input class="field-control" type="file" name="slip" required data-slip-input>
                @error('slip') <small class="field-error">{{ $message }}</small> @enderror
            </label>

            <label class="field">
                <span>Paid at (optional)</span>
                <input class="field-control" type="datetime-local" name="paid_at" value="{{ old('paid_at') }}">
                @error('paid_at') <small class="field-error">{{ $message }}</small> @enderror
            </label>
        </section>

        <div class="row-wrap" style="justify-content:space-between;">
            <a class="btn" href="{{ route('student.billing.subscription') }}">Back to plans</a>
            <button type="submit" class="btn btn-primary">Submit payment proof</button>
        </div>
    </form>
</div>
@endsection
