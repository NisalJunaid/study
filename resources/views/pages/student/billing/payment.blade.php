@extends('layouts.student', ['heading' => 'Payment confirmation', 'subheading' => 'Upload transfer proof to activate temporary access immediately.'])

@section('content')
<div class="stack-lg">
    <section class="card grid-2">
        <div class="stack-sm">
            <h2 class="h2">Selected plan</h2>
            <p class="mb-0"><strong>{{ $plan->name }}</strong> ({{ ucfirst($plan->type) }})</p>
            <p class="mb-0">Billing period: {{ \Illuminate\Support\Carbon::parse($breakdown['period_start'])->format('M d, Y') }} - {{ \Illuminate\Support\Carbon::parse($breakdown['period_end'])->format('M d, Y') }}</p>
            <p class="mb-0">Base monthly/annual rate: {{ $breakdown['currency'] }} {{ number_format((float) $breakdown['base_plan_amount'], 2) }}</p>
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

        <div class="stack-sm">
            <h2 class="h2">Bank transfer destination</h2>
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

    <section class="card">
        <h2 class="h2">Upload payment slip</h2>
        <p class="muted">After submission, you receive temporary access for up to 6 quizzes today while verification is pending for up to 24 hours.</p>

        <form class="stack-md" method="POST" action="{{ route('student.billing.payments.store') }}" enctype="multipart/form-data" data-slip-form>
            @csrf
            <input type="hidden" name="subscription_plan_id" value="{{ $plan->id }}">

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

            <div class="row-wrap">
                <a class="btn" href="{{ route('student.billing.subscription') }}">Back to plans</a>
                <button type="submit" class="btn btn-primary">Submit payment proof</button>
            </div>
        </form>
    </section>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.querySelector('[data-slip-form]');
    if (!form) return;

    const input = form.querySelector('[data-slip-input]');
    const preview = form.querySelector('[data-slip-preview]');
    const image = form.querySelector('[data-slip-image]');
    const fileName = form.querySelector('[data-slip-filename]');
    const nonImage = form.querySelector('[data-slip-non-image]');

    input.addEventListener('change', () => {
        const file = input.files?.[0];
        if (!file) {
            preview.hidden = true;
            return;
        }

        preview.hidden = false;
        fileName.textContent = file.name;

        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (event) => {
                image.src = event.target?.result;
                image.hidden = false;
                nonImage.hidden = true;
            };
            reader.readAsDataURL(file);
            return;
        }

        image.hidden = true;
        image.removeAttribute('src');
        nonImage.hidden = false;
    });
})();
</script>
@endpush
