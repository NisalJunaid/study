@extends($layout, ['heading' => $heading ?? 'Profile', 'subheading' => $subheading ?? null, 'contentWidthClass' => 'content-shell'])

@section('content')
<div class="stack-lg">
    <section class="card account-card">
        <div class="section-title">
            <h2 class="h1">Profile details</h2>
        </div>

        <form class="stack-md" method="POST" action="{{ route('profile.update') }}">
            @csrf
            @method('PATCH')

            <div class="grid-2">
                <label class="field">
                    <span>Full name</span>
                    <input id="name" class="field-control" type="text" name="name" value="{{ old('name', auth()->user()->name) }}" autocomplete="name" required>
                    @error('name') <small class="field-error">{{ $message }}</small> @enderror
                </label>

                <label class="field">
                    <span>Email address</span>
                    <input id="email" class="field-control" type="email" name="email" value="{{ old('email', auth()->user()->email) }}" autocomplete="email" required>
                    @error('email') <small class="field-error">{{ $message }}</small> @enderror
                </label>
            </div>

            <div class="account-meta-grid">
                <div class="summary-tile">
                    <p class="muted text-xs mb-0">Role</p>
                    <p class="text-strong mb-0">{{ auth()->user()->isAdmin() ? 'Admin' : 'Student' }}</p>
                </div>
                <div class="summary-tile">
                    <p class="muted text-xs mb-0">Avatar</p>
                    <div class="row-wrap" style="align-items:center;">
                        <span class="avatar-circle">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    </div>
                </div>
            </div>

            <div class="actions-row">
                <button type="submit" class="btn btn-primary">Save profile</button>
            </div>
        </form>
    </section>
</div>
@endsection
