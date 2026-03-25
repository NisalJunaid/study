<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreSubscriptionPaymentRequest;
use App\Models\PaymentSetting;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Services\Billing\QuizAccessService;
use App\Services\Billing\SubscriptionPaymentService;
use App\Support\OverlayMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function subscription(Request $request, QuizAccessService $quizAccessService): View
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

        return view('pages.student.billing.subscription', [
            'plans' => $plans,
            'subscription' => $user->subscriptions()->with('plan')->latest()->first(),
            'payments' => $user->payments()->with('plan')->latest('submitted_at')->limit(10)->get(),
            'access' => $quizAccessService->evaluate($user, 1),
            'trialRemaining' => $user->hasTrialRemaining(),
            'temporaryQuotaRemaining' => $user->temporaryQuizQuotaRemaining(),
            'selectedType' => $selectedType,
        ]);
    }

    public function selectPlan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subscription_plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
        ]);

        $plan = SubscriptionPlan::query()->active()->findOrFail((int) $data['subscription_plan_id']);
        $request->session()->put('billing.selected_plan_id', $plan->id);

        return redirect()->route('student.billing.payment');
    }

    public function payment(Request $request): View|RedirectResponse
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

        $discount = $plan->discounts->sortByDesc('amount')->first();
        $basePrice = (float) $plan->price;
        $discountAmount = 0.0;

        if ($discount) {
            $discountAmount = $discount->type === 'percentage'
                ? round($basePrice * ((float) $discount->amount / 100), 2)
                : min($basePrice, (float) $discount->amount);
        }

        return view('pages.student.billing.payment', [
            'plan' => $plan,
            'setting' => PaymentSetting::current(),
            'discount' => $discount,
            'basePrice' => $basePrice,
            'discountAmount' => $discountAmount,
            'amountDue' => max(0, $basePrice - $discountAmount),
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

        $payment = $subscriptionPaymentService->submitPayment(
            user: $user,
            plan: $plan,
            slip: $request->file('slip'),
            discountCode: $request->string('discount_code')->toString() ?: null,
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
