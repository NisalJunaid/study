@extends('layouts.admin', ['heading' => 'Payment Verifications'])

@section('content')
<x-admin.flash />
<div class="row-wrap" style="margin-bottom:.75rem">
    <a class="btn" href="{{ route('admin.billing.plans.index') }}">Manage plans</a>
    <a class="btn" href="{{ route('admin.billing.discounts.index') }}">Manage discounts</a>
    <a class="btn" href="{{ route('admin.billing.settings.edit') }}">Payment account settings</a>
</div>
<div class="card table-wrap">
    <table class="table">
        <thead><tr><th>Student</th><th>Plan</th><th>Submitted</th><th>Amount</th><th>Status</th><th>Slip</th><th>Actions</th></tr></thead>
        <tbody>
            @foreach($payments as $payment)
                <tr>
                    <td>{{ $payment->user->name }}<br><small class="muted">{{ $payment->user->email }}</small></td>
                    <td>{{ $payment->plan->name }} ({{ ucfirst($payment->plan->type) }})</td>
                    <td>{{ optional($payment->submitted_at)->format('M d, Y H:i') }}</td>
                    <td>{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</td>
                    <td><span class="pill">{{ ucfirst($payment->status) }}</span></td>
                    <td><a class="btn" href="{{ route('admin.billing.payments.slip', $payment) }}">Download</a></td>
                    <td>
                        @if($payment->status === \App\Models\SubscriptionPayment::STATUS_PENDING)
                            <div class="stack-sm">
                                <form method="POST" action="{{ route('admin.billing.payments.verify', $payment) }}">
                                    @csrf
                                    <button class="btn btn-primary" type="submit">Verify</button>
                                </form>
                                <form method="POST" action="{{ route('admin.billing.payments.reject', $payment) }}" class="stack-sm">
                                    @csrf
                                    <textarea class="field-control" name="reason" placeholder="Rejection reason" required></textarea>
                                    <button class="btn btn-danger" type="submit">Reject</button>
                                </form>
                            </div>
                        @else
                            <span class="muted">Reviewed</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    {{ $payments->links() }}
</div>
@endsection
