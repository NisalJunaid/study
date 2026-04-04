@extends('layouts.auth', ['title' => 'Verify Email'])

@section('content')
<section class="card stack-md">
    <h1 class="h2">Verify your email</h1>
    <p class="muted">Please confirm your email address before continuing.</p>

    @if (session('status') === 'verification-link-sent')
        <p class="text-sm">A new verification link has been sent to your email address.</p>
    @endif

    <form method="POST" action="{{ route('verification.send') }}" class="stack-sm">
        @csrf
        <button type="submit" class="btn btn-primary">Resend verification email</button>
    </form>
</section>
@endsection
