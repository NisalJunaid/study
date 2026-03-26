@extends('layouts.admin', ['heading' => 'Edit Billing Plan'])

@section('content')
<div class="card">
    <form method="POST" action="{{ route('admin.billing.plans.update', $plan) }}" class="stack-md">
        @csrf
        @method('PUT')
        @include('pages.admin.billing.plans.partials.form', ['plan' => $plan])
        <button class="btn btn-primary" type="submit">Save changes</button>
    </form>
</div>
@endsection
