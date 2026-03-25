@extends('layouts.admin', ['heading' => 'Create Billing Plan', 'subheading' => 'Add monthly or annual plan pricing.'])

@section('content')
<div class="card">
    <form method="POST" action="{{ route('admin.billing.plans.store') }}" class="stack-md">
        @csrf
        @include('pages.admin.billing.plans.partials.form', ['plan' => null])
        <button class="btn btn-primary" type="submit">Create plan</button>
    </form>
</div>
@endsection
