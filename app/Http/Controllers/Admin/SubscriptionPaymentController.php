<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectSubscriptionPaymentRequest;
use App\Models\SubscriptionPayment;
use App\Services\Billing\SubscriptionPaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SubscriptionPaymentController extends Controller
{
    public function index(): View
    {
        $payments = SubscriptionPayment::query()
            ->with(['user:id,name,email', 'plan:id,name,type'])
            ->latest('submitted_at')
            ->paginate(20);

        return view('pages.admin.billing.payments.index', compact('payments'));
    }

    public function verify(SubscriptionPayment $payment, SubscriptionPaymentService $service): RedirectResponse
    {
        if ($payment->status !== SubscriptionPayment::STATUS_PENDING) {
            return back()->with('error', 'Only pending payments can be verified.');
        }

        $service->verifyPayment($payment, request()->user());

        return back()->with('success', 'Payment verified and subscription activated.');
    }

    public function reject(
        RejectSubscriptionPaymentRequest $request,
        SubscriptionPayment $payment,
        SubscriptionPaymentService $service
    ): RedirectResponse {
        if ($payment->status !== SubscriptionPayment::STATUS_PENDING) {
            return back()->with('error', 'Only pending payments can be rejected.');
        }

        $service->rejectPayment($payment, $request->user(), (string) $request->string('reason'));

        return back()->with('success', 'Payment rejected and student access updated.');
    }

    public function slip(SubscriptionPayment $payment)
    {
        return response()->download(storage_path('app/'.$payment->slip_path), $payment->slip_original_name);
    }
}
