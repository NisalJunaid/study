@extends('layouts.student', ['heading' => 'Billing & Subscription', 'subheading' => 'Manage plan access, payment uploads, and verification status.'])

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
                @if($subscription->suspended_reason)
                    <p class="text-danger mb-0"><strong>Suspension reason:</strong> {{ $subscription->suspended_reason }}</p>
                @endif
            @endif
        </div>
    </section>

    <section class="card">
        <h2 class="h2">Choose a plan and upload payment proof</h2>
        <p class="muted">After slip upload, temporary access activates immediately (up to 6 quizzes for today) for 24 hours pending admin verification.</p>

        <form class="stack-md" method="POST" action="{{ route('student.billing.payments.store') }}" enctype="multipart/form-data">
            @csrf
            <label class="field">
                <span>Plan</span>
                <select class="field-control" name="subscription_plan_id" required>
                    <option value="">Select a plan</option>
                    @foreach($plans as $plan)
                        <option value="{{ $plan->id }}" @selected(old('subscription_plan_id') == $plan->id)>
                            {{ $plan->name }} ({{ ucfirst($plan->type) }}) - {{ $plan->currency }} {{ number_format((float) $plan->price, 2) }}
                        </option>
                    @endforeach
                </select>
                @error('subscription_plan_id') <small class="field-error">{{ $message }}</small> @enderror
            </label>

            <label class="field">
                <span>Discount code (optional)</span>
                <input class="field-control" type="text" name="discount_code" value="{{ old('discount_code') }}">
                @error('discount_code') <small class="field-error">{{ $message }}</small> @enderror
            </label>

            <label class="field">
                <span>Bank transfer slip (JPG, PNG, PDF, max 4MB)</span>
                <input class="field-control" type="file" name="slip" required>
                @error('slip') <small class="field-error">{{ $message }}</small> @enderror
            </label>


            <button type="submit" class="btn btn-primary">Submit payment proof</button>
        </form>
    </section>

    <section class="card">
        <h2 class="h2">Available plans and promotions</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Plan</th>
                        <th>Type</th>
                        <th>Price</th>
                        <th>Discounts</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($plans as $plan)
                        <tr>
                            <td>{{ $plan->name }}</td>
                            <td>{{ ucfirst($plan->type) }}</td>
                            <td>{{ $plan->currency }} {{ number_format((float) $plan->price, 2) }}</td>
                            <td>
                                @forelse($plan->discounts as $discount)
                                    <span class="pill">{{ $discount->name }} ({{ $discount->type === 'percentage' ? $discount->amount.'%' : $plan->currency.' '.$discount->amount }})</span>
                                @empty
                                    <span class="muted">No active discounts</span>
                                @endforelse
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h2 class="h2">Recent payment submissions</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Submitted</th>
                        <th>Plan</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Slip</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                        <tr>
                            <td>{{ optional($payment->submitted_at)->format('M d, Y H:i') }}</td>
                            <td>{{ $payment->plan?->name }}</td>
                            <td>{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</td>
                            <td><span class="pill">{{ ucfirst($payment->status) }}</span></td>
                            <td><a class="btn" href="{{ route('student.billing.payments.slip', $payment) }}">Download</a></td>
                        </tr>
                        @if($payment->rejection_reason)
                            <tr>
                                <td colspan="5" class="text-danger text-sm">Rejected reason: {{ $payment->rejection_reason }}</td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="5" class="muted">No submissions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
