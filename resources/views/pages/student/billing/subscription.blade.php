@extends('layouts.student', ['heading' => 'Choose your plan', 'subheading' => 'Pick monthly or annual billing before proceeding to payment.'])

@section('content')
<div class="stack-lg">
    <section class="card">
        <h2 class="h2">Access status</h2>
        <div class="stack-sm mt-2">
            <p class="mb-0"><strong>Current status:</strong> {{ $access['message'] ?? 'No status available.' }}</p>
            <p class="mb-0"><strong>Free trial:</strong> {{ $trialRemaining ? 'Available (1 quiz up to 10 questions)' : 'Used' }}</p>
            @if($temporaryQuotaRemaining > 0)
                <p class="mb-0"><strong>Temporary quota today:</strong> {{ $temporaryQuotaRemaining }} quiz(es) remaining.</p>
            @endif
            @if($subscription)
                <p class="mb-0"><strong>Subscription state:</strong> {{ ucfirst(str_replace('_', ' ', $subscription->status)) }}</p>
                @if($subscription->plan)
                    <p class="mb-0"><strong>Current plan:</strong> {{ $subscription->plan->name }} ({{ ucfirst($subscription->plan->type) }})</p>
                @endif
                @if($subscription->expires_at)
                    <p class="mb-0"><strong>Current access ends:</strong> {{ $subscription->expires_at->format('M d, Y') }}</p>
                @endif
            @endif
        </div>
    </section>

    <section class="card stack-md" data-plan-switcher>
        <div class="row-between">
            <h2 class="h2">Subscription options</h2>
            <div class="plan-toggle" role="tablist" aria-label="Billing cycle">
                <button type="button" class="btn plan-toggle-btn" data-plan-toggle="monthly" aria-selected="{{ $selectedType === 'monthly' ? 'true' : 'false' }}">Monthly</button>
                <button type="button" class="btn plan-toggle-btn" data-plan-toggle="annual" aria-selected="{{ $selectedType === 'annual' ? 'true' : 'false' }}">Annual</button>
            </div>
        </div>

        <div class="grid-2" data-plan-grid>
            @foreach($plans as $plan)
                @php($topDiscount = $plan->discounts->sortByDesc('amount')->first())
                <form method="POST" action="{{ route('student.billing.subscription.select-plan') }}" class="card plan-card" data-plan-type="{{ $plan->type }}">
                    @csrf
                    <input type="hidden" name="subscription_plan_id" value="{{ $plan->id }}">
                    <div class="stack-sm">
                        <h3 class="h3">{{ $plan->name }}</h3>
                        <p class="muted mb-0">{{ $plan->description ?: 'Reliable access with full question bank support.' }}</p>
                        @php($state = $planStates[$plan->id] ?? null)
                        <p class="plan-price mb-0">{{ $plan->currency }} {{ number_format((float)$plan->price, 2) }}</p>
                        @if($state)
                            <p class="mb-0 text-sm"><strong>Billing period:</strong> {{ \Illuminate\Support\Carbon::parse($state['period']['start'])->format('M d, Y') }} - {{ \Illuminate\Support\Carbon::parse($state['period']['end'])->format('M d, Y') }}</p>
                            <p class="mb-0 text-sm"><strong>Total due:</strong> {{ $state['pricing']['currency'] }} {{ number_format((float) $state['pricing']['total_due'], 2) }}</p>
                            @if($state['pricing']['is_prorated'])
                                <p class="mb-0 text-sm muted">Prorated for remaining days this month.</p>
                            @endif
                            @if(($state['pricing']['registration_fee'] ?? 0) > 0)
                                <p class="mb-0 text-sm muted">Includes one-time registration fee.</p>
                            @endif
                            <p class="mb-0 text-sm {{ $state['can_select'] ? 'text-success' : 'muted' }}">{{ $state['message'] }}</p>
                        @endif
                        @if($topDiscount)
                            <p class="pill mb-0">{{ $topDiscount->name }}: {{ $topDiscount->type === 'percentage' ? $topDiscount->amount.'%' : $plan->currency.' '.number_format((float)$topDiscount->amount, 2) }} off</p>
                        @endif
                        @if($state && $state['can_select'])
                            <button class="btn btn-primary" type="submit">Continue to payment</button>
                        @else
                            <button class="btn" type="button" disabled>Not available now</button>
                        @endif
                    </div>
                </form>
            @endforeach
        </div>
    </section>

    <section class="card">
        <h2 class="h2">Recent payment submissions</h2>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Submitted</th><th>Plan</th><th>Amount</th><th>Status</th><th>Slip</th></tr></thead>
                <tbody>
                    @forelse($payments as $payment)
                        <tr>
                            <td>{{ optional($payment->submitted_at)->format('M d, Y H:i') }}</td>
                            <td>{{ $payment->plan?->name }}</td>
                            <td>{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</td>
                            <td><span class="pill">{{ ucfirst($payment->status) }}</span></td>
                            <td><a class="btn" href="{{ route('student.billing.payments.slip', $payment) }}">Download</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">No submissions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const root = document.querySelector('[data-plan-switcher]');
    if (!root) return;

    const cards = root.querySelectorAll('[data-plan-type]');
    const toggleButtons = root.querySelectorAll('[data-plan-toggle]');

    const update = (type) => {
        cards.forEach((card) => {
            card.style.display = card.dataset.planType === type ? 'block' : 'none';
        });

        toggleButtons.forEach((button) => {
            const selected = button.dataset.planToggle === type;
            button.setAttribute('aria-selected', selected ? 'true' : 'false');
            button.classList.toggle('btn-primary', selected);
        });
    };

    toggleButtons.forEach((button) => button.addEventListener('click', () => update(button.dataset.planToggle)));

    const initial = [...toggleButtons].find((b) => b.getAttribute('aria-selected') === 'true')?.dataset.planToggle ?? 'monthly';
    update(initial);
})();
</script>
@endpush
