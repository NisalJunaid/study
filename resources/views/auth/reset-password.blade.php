@extends('layouts.auth', ['title' => 'Reset Password'])

@section('content')
<section class="card stack-md">
    <h1 class="h2">Reset password</h1>

    <form method="POST" action="{{ route('password.store') }}" class="stack-sm">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <label class="input-field">
            <span>Email</span>
            <input type="email" class="field-control" name="email" value="{{ old('email', $email) }}" required autofocus>
            @error('email') <small class="field-error">{{ $message }}</small> @enderror
        </label>

        <label class="input-field">
            <span>Password</span>
            <input type="password" class="field-control" name="password" required>
            @error('password') <small class="field-error">{{ $message }}</small> @enderror
        </label>

        <label class="input-field">
            <span>Confirm password</span>
            <input type="password" class="field-control" name="password_confirmation" required>
        </label>

        <button type="submit" class="btn btn-primary">Reset password</button>
    </form>
</section>
@endsection
