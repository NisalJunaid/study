@extends('layouts.auth', [
    'title' => 'Focus Lab | Log in',
    'heroTitle' => 'Welcome back to Focus Lab',
    'heroCopy' => "Continue your study routine, pick up where you left off, and keep moving toward exam success.",
])

@section('content')
    <div class="focus-auth-form-shell stack-lg">
        <header class="stack-sm">
            <p class="pill">Account Access</p>
            <h2 class="h1">Log in</h2>
            <p class="muted mb-0">Sign in to continue your quiz journey.</p>
        </header>

        @if (session('status'))
            <div class="alert alert-success mb-0">
                <p class="mb-0">{{ session('status') }}</p>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="stack-md focus-auth-main-form">
            @csrf

            <label class="input-field">
                <span>Email</span>
                <input
                    class="field-control"
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    autocomplete="username"
                    required
                    autofocus
                >
                @error('email') <small class="field-error">{{ $message }}</small> @enderror
            </label>

            <label class="input-field">
                <span>Password</span>
                <input
                    class="field-control"
                    id="password"
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >
                @error('password') <small class="field-error">{{ $message }}</small> @enderror
            </label>

            <label class="checkbox-row focus-auth-checkbox">
                <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                <span>Keep me signed in on this device</span>
            </label>

            <div class="focus-auth-form-cta stack-sm">
                <button type="submit" class="btn btn-primary w-full">Log in</button>

                <div class="focus-auth-inline-links">
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="btn btn-ghost">Forgot password?</a>
                    @endif
                </div>
            </div>
        </form>

        <footer class="focus-auth-secondary-action">
            <p class="text-sm muted mb-0">Don't have an account?</p>
            <a href="{{ route('register') }}" class="focus-auth-secondary-link">Create one here</a>
        </footer>
    </div>
@endsection
