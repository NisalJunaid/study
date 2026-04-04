@extends('layouts.auth', ['title' => 'Forgot Password'])

@section('content')
<section class="card stack-md">
    <h1 class="h2">Forgot your password?</h1>
    <p class="muted">Enter your email and we will send a reset link.</p>

    @if (session('status'))
        <p class="text-sm">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="stack-sm">
        @csrf
        <label class="input-field">
            <span>Email</span>
            <input type="email" class="field-control" name="email" value="{{ old('email') }}" required autofocus>
            @error('email') <small class="field-error">{{ $message }}</small> @enderror
        </label>
        <button type="submit" class="btn btn-primary">Email password reset link</button>
    </form>
</section>
@endsection
