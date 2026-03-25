@extends($layout, ['heading' => $heading ?? 'Settings', 'subheading' => $subheading ?? 'Update your account security preferences.', 'contentWidthClass' => 'content-shell'])

@section('content')
<div class="stack-lg">
    <section class="card account-card">
        <div class="section-title">
            <h2 class="h1">Security settings</h2>
            <p class="muted">Change your password to keep your account secure.</p>
        </div>
        @if(auth()->user() && ! auth()->user()->isAdmin())
            <p class="muted">Need to manage plan payments? <a href="{{ route('student.billing.subscription') }}">Open billing settings</a>.</p>
        @endif

        @if(($status ?? null) === 'password-updated')
            <div class="alert alert-success" role="status">Password updated successfully.</div>
        @endif

        <form class="stack-md" method="POST" action="{{ route('password.update') }}">
            @csrf
            @method('PUT')

            <div class="grid-2">
                <label class="field">
                    <span>Current password</span>
                    <input class="field-control" type="password" name="current_password" autocomplete="current-password" required>
                    @error('current_password') <small class="field-error">{{ $message }}</small> @enderror
                </label>

                <label class="field">
                    <span>New password</span>
                    <input class="field-control" type="password" name="password" autocomplete="new-password" required>
                    @error('password') <small class="field-error">{{ $message }}</small> @enderror
                </label>
            </div>

            <label class="field">
                <span>Confirm new password</span>
                <input class="field-control" type="password" name="password_confirmation" autocomplete="new-password" required>
            </label>

            <p class="muted text-sm mb-0">Use a strong password with a mix of letters, numbers, and symbols.</p>

            <div class="actions-row">
                <button type="submit" class="btn btn-primary">Update password</button>
            </div>
        </form>
    </section>
</div>
@endsection
