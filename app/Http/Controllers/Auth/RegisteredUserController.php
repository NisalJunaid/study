<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PaymentSetting;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Billing\BillingEligibilityService;
use App\Services\Billing\SubscriptionPaymentService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(BillingEligibilityService $billingEligibilityService): View
    {
        $plans = SubscriptionPlan::query()
            ->active()
            ->with(['discounts' => fn ($query) => $query->currentlyActive()->orderByDesc('amount')])
            ->orderBy('sort_order')
            ->get();

        $guestStudent = new User([
            'role' => User::ROLE_STUDENT,
            'onboarding_intent' => User::ONBOARDING_SUBSCRIBE,
        ]);

        $planStates = [];
        foreach ($plans as $plan) {
            $planStates[$plan->id] = $billingEligibilityService->describePlanState($guestStudent, $plan);
        }

        return view('auth.register', [
            'plans' => $plans,
            'planStates' => $planStates,
            'paymentSetting' => PaymentSetting::current(),
        ]);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(
        Request $request,
        SubscriptionPaymentService $subscriptionPaymentService
    ): RedirectResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'id_document_number' => ['required', 'string', 'max:100'],
            'nationality' => ['required', 'string', 'max:100'],
            'contact_number' => ['required', 'string', 'max:40'],
            'id_document' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'subscription_plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'slip' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);

        $plan = SubscriptionPlan::query()
            ->active()
            ->findOrFail((int) $validated['subscription_plan_id']);

        try {
            $user = DB::transaction(function () use ($validated, $request, $plan, $subscriptionPaymentService): User {
                $idDocument = $request->file('id_document');
                $idDocumentPath = $idDocument->store('identity-documents');

                $user = User::create([
                    'name' => $validated['name'],
                    'email' => strtolower($validated['email']),
                    'password' => Hash::make($validated['password']),
                    'role' => User::ROLE_STUDENT,
                    'onboarding_intent' => User::ONBOARDING_SUBSCRIBE,
                    'id_document_number' => $validated['id_document_number'],
                    'nationality' => $validated['nationality'],
                    'contact_number' => $validated['contact_number'],
                    'id_document_path' => $idDocumentPath,
                    'id_document_original_name' => $idDocument->getClientOriginalName(),
                ]);

                $state = $subscriptionPaymentService->ensureUserCanSubmit($user, $plan);
                $subscriptionPaymentService->submitPayment(
                    user: $user,
                    plan: $plan,
                    slip: $request->file('slip'),
                    discountCode: null,
                    eligibilityState: $state,
                );

                return $user;
            });
        } catch (QueryException $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'email' => 'We could not create your account right now. Please try again shortly.',
            ]);
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect()
            ->route('student.billing.subscription')
            ->with('success', 'Registration completed. Your payment is pending verification and temporary access is enabled.');
    }
}
