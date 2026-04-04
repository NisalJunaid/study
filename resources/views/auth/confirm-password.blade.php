@extends('layouts.auth', ['title' => 'Confirm Password'])

@section('content')
<section class="card stack-md">
    <h1 class="h2">Confirm password</h1>
    <p class="muted">Please confirm your password before continuing.</p>

    <form method="POST" action="{{ route('password.confirm') }}" class="stack-sm">
        @csrf
        <label class="input-field">
            <span>Password</span>
            <input type="password" class="field-control" name="password" required autocomplete="current-password">
            @error('password') <small class="field-error">{{ $message }}</small> @enderror
        </label>

        <button type="submit" class="btn btn-primary">Confirm</button>
    </form>
</section>
@endsection
