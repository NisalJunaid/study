@extends('layouts.student', ['heading' => 'Choose your plan', 'subheading' => 'Use the guided steps to understand access, select a plan, and continue to payment.'])

@section('content')
<div class="stack-lg" id="guided-billing-subscription" data-initial-step="1">
    <x-guided.stepper
        :steps="['Check access status', 'Choose plan type', 'Select plan', 'Review & continue']"
        :current="1"
        label="Subscription setup progress"
    />

    <form method="POST" action="{{ route('student.billing.subscription.select-plan') }}" class="card stack-lg" data-guided-subscription-form>
        @csrf

        <section class="guided-step-pane stack-sm" data-guided-step="1">
            <h2 class="h2">Step 1: Understand your current access</h2>
            <p class="muted mb-0">Review your current status before choosing a new plan.</p>
            <div class="guided-summary">
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

        <section class="guided-step-pane stack-md" data-guided-step="2" hidden>
            <h2 class="h2">Step 2: Choose a billing cycle</h2>
            <p class="muted mb-0">Switch between monthly and annual plans first.</p>
            <div class="plan-toggle" role="tablist" aria-label="Billing cycle">
                <button type="button" class="btn plan-toggle-btn" data-plan-toggle="monthly" aria-selected="{{ $selectedType === 'monthly' ? 'true' : 'false' }}">Monthly</button>
                <button type="button" class="btn plan-toggle-btn" data-plan-toggle="annual" aria-selected="{{ $selectedType === 'annual' ? 'true' : 'false' }}">Annual</button>
            </div>
            <small class="field-error" data-step-error="2" hidden></small>
        </section>

        <section class="guided-step-pane stack-md" data-guided-step="3" hidden>
            <h2 class="h2">Step 3: Select your plan</h2>
            <p class="muted mb-0">Only available plans can continue to payment.</p>
            <div class="grid-2" data-plan-grid>
                @foreach($plans as $plan)
                    @php
                        $topDiscount = $plan->discounts->sortByDesc('amount')->first();
                        $state = $planStates[$plan->id] ?? null;
                    @endphp
                    <label class="card plan-card" data-plan-type="{{ $plan->type }}" style="cursor:pointer;">
                        <input type="radio" name="subscription_plan_id" value="{{ $plan->id }}" data-plan-picker @disabled(!($state['can_select'] ?? false))>
                        <div class="stack-sm mt-2">
                            <h3 class="h3">{{ $plan->name }}</h3>
                            <p class="muted mb-0">{{ $plan->description ?: 'Reliable access with full question bank support.' }}</p>
                            <p class="plan-price mb-0">{{ $plan->currency }} {{ number_format((float)$plan->price, 2) }}</p>
                            @if($state)
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
                        </div>
                    </label>
                @endforeach
            </div>
            @error('subscription_plan_id') <small class="field-error">{{ $message }}</small> @enderror
            <small class="field-error" data-step-error="3" hidden></small>
        </section>

        <section class="guided-step-pane stack-sm" data-guided-step="4" hidden>
            <h2 class="h2">Step 4: Review and continue</h2>
            <p class="muted mb-0">You will review final pricing and upload your payment slip on the next page.</p>
            <div class="guided-summary" data-subscription-summary>
                <p class="mb-0 muted">Select a plan to see review details.</p>
            </div>
        </section>

        <div class="actions-row row-between">
            <button type="button" class="btn" data-guided-prev>Back</button>
            <div class="row-wrap">
                <button type="button" class="btn btn-primary" data-guided-next>Next</button>
                <button type="submit" class="btn btn-primary">Continue to payment</button>
            </div>
        </div>
    </form>

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
