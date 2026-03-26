<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreSubscriptionPaymentRequest;
use App\Models\PaymentSetting;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Services\Billing\BillingEligibilityService;
use App\Services\Billing\QuizAccessService;
use App\Services\Billing\SubscriptionPaymentService;
use App\Support\OverlayMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function subscription(
        Request $request,
        QuizAccessService $quizAccessService,
        BillingEligibilityService $billingEligibilityService
    ): View
    {
        $user = $request->user();
        $plans = SubscriptionPlan::query()
            ->active()
            ->with(['discounts' => fn ($query) => $query->currentlyActive()->orderByDesc('amount')])
            ->orderBy('sort_order')
            ->get();

        $selectedType = $request->string('type')->toString();
        if (! in_array($selectedType, [SubscriptionPlan::TYPE_MONTHLY, SubscriptionPlan::TYPE_ANNUAL], true)) {
            $selectedType = SubscriptionPlan::TYPE_MONTHLY;
        }

        $planStates = [];
        foreach ($plans as $plan) {
            $planStates[$plan->id] = $billingEligibilityService->describePlanState($user, $plan);
        }

        $subscription = $user->subscriptions()->with('plan')->latest()->first();
        $latestPayment = $user->payments()->with('plan')->latest('submitted_at')->first();
        $showPlanFlow = $request->boolean('start_payment');
        $isChangePlanFlow = $request->boolean('change_plan');
        if (
            $subscription
            && $subscription->status === UserSubscription::STATUS_ACTIVE
            && ! $isChangePlanFlow
        ) {
            $showPlanFlow = false;
        }

        return view('pages.student.billing.subscription', [
            'plans' => $plans,
            'subscription' => $subscription,
            'payments' => $user->payments()->with('plan')->latest('submitted_at')->limit(10)->get(),
            'access' => $quizAccessService->evaluate($user, 1),
            'trialRemaining' => $user->hasTrialRemaining(),
            'temporaryQuotaRemaining' => $user->temporaryQuizQuotaRemaining(),
            'selectedType' => $selectedType,
            'planStates' => $planStates,
            'latestPayment' => $latestPayment,
            'showPlanFlow' => $showPlanFlow,
            'isChangePlanFlow' => $isChangePlanFlow,
            'activePlanId' => ($subscription && $subscription->status === UserSubscription::STATUS_ACTIVE)
                ? (int) $subscription->subscription_plan_id
                : null,
        ]);
    }

    public function selectPlan(Request $request, BillingEligibilityService $billingEligibilityService): RedirectResponse
    {
        $data = $request->validate([
            'subscription_plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
        ]);

        $plan = SubscriptionPlan::query()->active()->findOrFail((int) $data['subscription_plan_id']);
        $state = $billingEligibilityService->describePlanState($request->user(), $plan);
        if (! $state['can_select']) {
            return redirect()->route('student.billing.subscription')->withErrors([
                'subscription_plan_id' => $state['message'],
            ]);
        }

        $request->session()->put('billing.selected_plan_id', $plan->id);

        return redirect()->route('student.billing.payment');
    }

    public function payment(Request $request, BillingEligibilityService $billingEligibilityService): View|RedirectResponse
    {
        $user = $request->user();
        $planId = (int) $request->session()->get('billing.selected_plan_id', 0);
        $plan = SubscriptionPlan::query()
            ->active()
            ->with(['discounts' => fn ($query) => $query->currentlyActive()->orderByDesc('amount')])
            ->find($planId);

        if (! $plan) {
            return redirect()
                ->route('student.billing.subscription')
                ->with('overlay', OverlayMessage::redirect(
                    'Plan required',
                    'Select a subscription plan to continue to payment.',
                    route('student.billing.subscription'),
                    'info',
                    ['primary_label' => 'Choose a Plan', 'blocking' => false, 'dismissible' => true],
                ));
        }

        $state = $billingEligibilityService->describePlanState($user, $plan);
        if (! $state['can_select']) {
            return redirect()
                ->route('student.billing.subscription')
                ->withErrors(['subscription_plan_id' => $state['message']]);
        }

        return view('pages.student.billing.payment', [
            'plan' => $plan,
            'setting' => PaymentSetting::current(),
            'breakdown' => $state['pricing'],
            'period' => $state['period'],
            'state' => $state,
            'isSuspended' => (bool) optional($user->subscriptions()->latest()->first())->isSuspended(),
        ]);
    }

    public function storePayment(
        StoreSubscriptionPaymentRequest $request,
        SubscriptionPaymentService $subscriptionPaymentService
    ): RedirectResponse {
        $user = $request->user();

        $plan = SubscriptionPlan::query()
            ->active()
            ->findOrFail((int) $request->integer('subscription_plan_id'));
        $state = $subscriptionPaymentService->ensureUserCanSubmit($user, $plan);

        $payment = $subscriptionPaymentService->submitPayment(
            user: $user,
            plan: $plan,
            slip: $request->file('slip'),
            discountCode: $request->string('discount_code')->toString() ?: null,
            eligibilityState: $state,
        );

        if ($request->filled('paid_at')) {
            $payment->update(['paid_at' => $request->date('paid_at')]);
        }

        return redirect()
            ->route('student.billing.subscription')
            ->with('overlay', OverlayMessage::paymentSubmitted());
    }

    public function slip(SubscriptionPayment $payment)
    {
        abort_unless($payment->user_id === request()->user()->id, 403);

        return response()->download(storage_path('app/'.$payment->slip_path), $payment->slip_original_name);
    }
}
