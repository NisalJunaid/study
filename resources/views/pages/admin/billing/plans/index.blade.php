@extends('layouts.admin', ['heading' => 'Billing Plans'])

@section('content')
<x-admin.flash />
<div class="stack-lg">
    <div class="row-between">
        <h2 class="h2">Subscription plans</h2>
        <a class="btn btn-primary" href="{{ route('admin.billing.plans.create') }}">+ New plan</a>
    </div>

    <div class="card table-wrap">
        <table class="table">
            <thead><tr><th>Name</th><th>Code</th><th>Type</th><th>Price</th><th>Status</th><th>Subscribers</th><th>Actions</th></tr></thead>
            <tbody>
                @foreach($plans as $plan)
                    <tr>
                        <td>{{ $plan->name }}</td>
                        <td><code>{{ $plan->code }}</code></td>
                        <td>{{ ucfirst($plan->type) }}</td>
                        <td>{{ $plan->currency }} {{ number_format((float) $plan->price, 2) }}</td>
                        <td>{{ $plan->is_active ? 'Active' : 'Disabled' }}</td>
                        <td>{{ $plan->subscriptions_count }}</td>
                        <td class="actions-inline">
                            <a class="btn" href="{{ route('admin.billing.plans.edit', $plan) }}">Edit</a>
                            <form method="POST" action="{{ route('admin.billing.plans.destroy', $plan) }}" data-confirm-title="Delete plan" data-confirm-message="Delete this plan?" data-confirm-variant="danger" data-confirm-primary="Delete" data-confirm-secondary="Cancel">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-danger" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        {{ $plans->links() }}
    </div>
</div>
@endsection
