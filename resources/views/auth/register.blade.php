@extends('layouts.auth', [
    'title' => 'Focus Lab | Get Started',
    'heroTitle' => 'Start your Focus Lab learning account',
    'heroCopy' => "Complete the guided registration flow to unlock your personalized quiz workspace.",
    'shellClass' => 'focus-auth-shell-wide',
    'mainId' => 'guided-register-root',
])

@section('content')
    <section class="onboarding-panel focus-auth-onboarding-panel card stack-lg">
        <header class="stack-sm">
            <p class="pill">Get Started</p>
            <h2 class="h1">Create Account</h2>
            <p class="muted mb-0">Three quick steps and you're ready to learn.</p>
        </header>

        @if ($errors->any())
            <div class="alert alert-error">
                <p class="mb-0">Please check the highlighted fields and continue.</p>
            </div>
        @endif

        <div class="onboarding-progress" data-onboarding-progress>
            <div class="onboarding-progress-bar" data-onboarding-progress-bar></div>
        </div>

        <div class="onboarding-step-indicators">
            <button type="button" class="onboarding-step-indicator active" data-step-indicator="1">
                <span>1</span> Personal
            </button>
            <button type="button" class="onboarding-step-indicator" data-step-indicator="2">
                <span>2</span> Plan
            </button>
            <button type="button" class="onboarding-step-indicator" data-step-indicator="3">
                <span>3</span> Billing
            </button>
        </div>

        @php
            $stepFieldMap = [
                1 => ['name', 'email', 'password', 'id_document_number', 'nationality', 'contact_number', 'id_document'],
                2 => ['subscription_plan_id'],
                3 => ['slip'],
            ];

            $initialStep = 1;
            foreach ($stepFieldMap as $step => $fields) {
                foreach ($fields as $field) {
                    if ($errors->has($field)) {
                        $initialStep = $step;
                        break 2;
                    }
                }
            }
        @endphp

        <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data" data-onboarding-form data-initial-step="{{ $initialStep }}">
            @csrf

            <section class="stack-md" data-step="1" data-step-panel>
                <h2 class="h2">Personal Details</h2>
                <div class="onboarding-grid-2">
                    <label class="input-field">
                        <span>Full Name</span>
                        <input class="field-control" type="text" name="name" value="{{ old('name') }}" required>
                        @error('name') <small class="field-error">{{ $message }}</small> @enderror
                    </label>
                    <label class="input-field">
                        <span>Email</span>
                        <input class="field-control" type="email" name="email" value="{{ old('email') }}" required>
                        @error('email') <small class="field-error">{{ $message }}</small> @enderror
                    </label>
                    <label class="input-field">
                        <span>ID Card No / Passport No</span>
                        <input class="field-control" type="text" name="id_document_number" value="{{ old('id_document_number') }}" required>
                        @error('id_document_number') <small class="field-error">{{ $message }}</small> @enderror
                    </label>
                    <label class="input-field">
                        <span>Nationality</span>
                        <input class="field-control" type="text" name="nationality" value="{{ old('nationality') }}" required>
                        @error('nationality') <small class="field-error">{{ $message }}</small> @enderror
                    </label>
                    <label class="input-field">
                        <span>Contact Number</span>
                        <input class="field-control" type="text" name="contact_number" value="{{ old('contact_number') }}" required>
                        @error('contact_number') <small class="field-error">{{ $message }}</small> @enderror
                    </label>
                </div>

                <div class="onboarding-grid-2">
                    <label class="input-field">
                        <span>Password</span>
                        <input class="field-control" type="password" name="password" required>
                        @error('password') <small class="field-error">{{ $message }}</small> @enderror
                    </label>
                    <label class="input-field">
                        <span>Confirm Password</span>
                        <input class="field-control" type="password" name="password_confirmation" required>
                    </label>
                </div>

                <label class="upload-preview" data-upload-preview>
                    <input class="field-control payment-upload-input" type="file" name="id_document" accept=".jpg,.jpeg,.png,.pdf" data-upload-input required>
                    <div class="row-between">
                        <p class="h3 mb-0">ID Card Upload</p>
                        <span class="btn btn-ghost">Choose file</span>
                    </div>
                    <p class="text-sm mb-0"><strong>Selected file:</strong> <span data-upload-filename>No file selected yet.</span></p>
                    <p class="muted text-sm mb-0" data-upload-placeholder>Upload JPG, PNG, or PDF (max 4MB).</p>
                    <p class="muted text-sm mb-0" data-upload-fallback hidden>Preview unavailable for this file type.</p>
                    <img data-upload-image alt="ID document preview" hidden>
                    @error('id_document') <small class="field-error">{{ $message }}</small> @enderror
                </label>
            </section>

            <section class="stack-md" data-step="2" data-step-panel hidden>
                <h2 class="h2">Plan Selection</h2>
                <div class="onboarding-plan-grid" data-plan-grid>
                    @foreach($plans as $plan)
                        @php($state = $planStates[$plan->id] ?? null)
                        <label class="plan-card plan-card-selectable" data-plan-card>
                            <input
                                type="radio"
                                name="subscription_plan_id"
                                value="{{ $plan->id }}"
                                data-plan-picker
                                data-plan-name="{{ $plan->name }}"
                                data-plan-label="{{ ucfirst($plan->type) }}"
                                data-base="{{ $state['pricing']['base_plan_amount'] ?? $plan->price }}"
                                data-prorated="{{ $state['pricing']['prorated_plan_amount'] ?? $plan->price }}"
                                data-registration="{{ $state['pricing']['registration_fee'] ?? 0 }}"
                                data-total="{{ $state['pricing']['total_due'] ?? $plan->price }}"
                                data-currency="{{ $state['pricing']['currency'] ?? $plan->currency }}"
                                data-is-prorated="{{ !empty($state['pricing']['is_prorated']) ? '1' : '0' }}"
                                @checked(old('subscription_plan_id') == $plan->id)
                                required
                            >
                            <div class="stack-sm">
                                <div class="row-between">
                                    <h3 class="h3">{{ $plan->name }}</h3>
                                    <span class="pill">{{ ucfirst($plan->type) }}</span>
                                </div>
                                <p class="plan-price mb-0">{{ $plan->currency }} {{ number_format((float) $plan->price, 2) }}</p>
                                @if($state)
                                    <p class="muted text-sm mb-0">{{ $state['message'] }}</p>
                                @endif
                            </div>
                        </label>
                    @endforeach
                </div>
                @error('subscription_plan_id') <small class="field-error">{{ $message }}</small> @enderror
            </section>

            <section class="stack-md" data-step="3" data-step-panel hidden>
                <h2 class="h2">Billing & Payment</h2>

                <article class="card stack-sm onboarding-billing-summary">
                    <h3 class="h3">Pricing Summary</h3>
                    <div class="billing-line"><span>Selected Plan</span><strong data-summary-plan>Choose a plan</strong></div>
                    <div class="billing-line"><span>Actual Price</span><strong data-summary-base>—</strong></div>
                    <div class="billing-line"><span>Prorated Price</span><strong data-summary-prorated>—</strong></div>
                    <div class="billing-line"><span>Registration Fee</span><strong data-summary-registration>—</strong></div>
                    <div class="billing-line total"><span>Total Payable</span><strong data-summary-total>—</strong></div>
                </article>

                <article class="card stack-sm">
                    <h3 class="h3">Bank Details</h3>
                    <div class="billing-line"><span>Account Name</span><strong>{{ $paymentSetting->bank_account_name }}</strong></div>
                    <div class="billing-line">
                        <span>Account Number</span>
                        <strong class="mono" data-bank-account>{{ $paymentSetting->bank_account_number }}</strong>
                    </div>
                    <div class="row-wrap">
                        <button type="button" class="btn" data-copy-account>Copy Account Number</button>
                        <small class="muted" data-copy-feedback hidden>Copied.</small>
                    </div>
                    <div class="billing-line"><span>Bank Name</span><strong>{{ $paymentSetting->bank_name ?: '—' }}</strong></div>
                </article>

                <label class="upload-preview" data-upload-preview>
                    <input class="field-control payment-upload-input" type="file" name="slip" accept=".jpg,.jpeg,.png,.pdf" data-upload-input required>
                    <div class="row-between">
                        <p class="h3 mb-0">Payment Slip Upload</p>
                        <span class="btn btn-ghost">Choose file</span>
                    </div>
                    <p class="text-sm mb-0"><strong>Selected file:</strong> <span data-upload-filename>No file selected yet.</span></p>
                    <p class="muted text-sm mb-0" data-upload-placeholder>Upload JPG, PNG, or PDF (max 4MB).</p>
                    <p class="muted text-sm mb-0" data-upload-fallback hidden>Preview unavailable for this file type.</p>
                    <img data-upload-image alt="Payment slip preview" hidden>
                    @error('slip') <small class="field-error">{{ $message }}</small> @enderror
                </label>
            </section>

            <footer class="onboarding-actions focus-auth-onboarding-actions">
                <div class="row-wrap">
                    <button type="button" class="btn btn-ghost" data-prev-step hidden>Previous</button>
                    <button type="button" class="btn btn-primary" data-next-step>Next</button>
                    <button type="submit" class="btn btn-primary" data-submit-step hidden>Complete Registration</button>
                </div>
            </footer>

            <div class="focus-auth-secondary-action">
                <p class="text-sm muted mb-0">Already have an account?</p>
                <a href="{{ route('login') }}" class="focus-auth-secondary-link">Log in instead</a>
            </div>
        </form>
    </section>
@endsection
