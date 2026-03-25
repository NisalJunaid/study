<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreSubscriptionPaymentRequest;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Services\Billing\QuizAccessService;
use App\Services\Billing\SubscriptionPaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class BillingController extends Controller
{
    public function index(QuizAccessService $quizAccessService): View
    {
        $user = request()->user();

        $plans = SubscriptionPlan::query()
            ->active()
            ->with(['discounts' => fn ($query) => $query->currentlyActive()->orderBy('amount', 'desc')])
            ->orderBy('sort_order')
            ->get();

        $subscription = $user->subscriptions()->with('plan')->latest()->first();
        $payments = $user->payments()->with('plan')->latest('submitted_at')->limit(10)->get();
        $access = $quizAccessService->evaluate($user, 1);

        return view('pages.student.billing.index', [
            'plans' => $plans,
            'subscription' => $subscription,
            'payments' => $payments,
            'access' => $access,
            'trialRemaining' => $user->hasTrialRemaining(),
            'temporaryQuotaRemaining' => $user->temporaryQuizQuotaRemaining(),
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
            ->route('student.billing.index')
            ->with('success', 'Payment proof submitted. Temporary access is now active for up to 6 quizzes today while admin verification is pending.');
    }

    public function slip(SubscriptionPayment $payment)
    {
        abort_unless($payment->user_id === request()->user()->id, 403);

        return response()->download(storage_path('app/'.$payment->slip_path), $payment->slip_original_name);
    }
}
