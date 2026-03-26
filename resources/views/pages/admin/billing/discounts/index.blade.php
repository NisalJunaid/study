@extends('layouts.admin', ['heading' => 'Plan Discounts'])

@section('content')
<x-admin.flash />
<div class="stack-lg">
    <section class="card">
        <h2 class="h2">Add discount</h2>
        <form method="POST" action="{{ route('admin.billing.discounts.store') }}" class="grid-3">
            @csrf
            <select class="field-control" name="subscription_plan_id" required>
                <option value="">Plan</option>
                @foreach($plans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }}</option>@endforeach
            </select>
            <input class="field-control" name="name" placeholder="Discount name" required>
            <input class="field-control" name="code" placeholder="Code (optional)">
            <select class="field-control" name="type" required><option value="percentage">Percentage</option><option value="fixed">Fixed</option></select>
            <input class="field-control" type="number" step="0.01" name="amount" placeholder="Amount" required>
            <input class="field-control" type="datetime-local" name="starts_at">
            <input class="field-control" type="datetime-local" name="ends_at">
            <label><input type="checkbox" name="is_active" value="1" checked> Active</label>
            <button class="btn btn-primary" type="submit">Create</button>
        </form>
    </section>

    <section class="card table-wrap">
        <table class="table">
            <thead><tr><th>Name</th><th>Plan</th><th>Type</th><th>Amount</th><th>Window</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                @foreach($discounts as $discount)
                    <tr>
                        <td>{{ $discount->name }} @if($discount->code)<span class="pill">{{ $discount->code }}</span>@endif</td>
                        <td>{{ $discount->plan?->name }}</td>
                        <td>{{ $discount->type }}</td>
                        <td>{{ $discount->amount }}</td>
                        <td>{{ optional($discount->starts_at)->format('M d Y H:i') ?: 'Any' }} - {{ optional($discount->ends_at)->format('M d Y H:i') ?: 'Open' }}</td>
                        <td>{{ $discount->is_active ? 'Active' : 'Disabled' }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.billing.discounts.destroy', $discount) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        {{ $discounts->links() }}
    </section>
</div>
@endsection
