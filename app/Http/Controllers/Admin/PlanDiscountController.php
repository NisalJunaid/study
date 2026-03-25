<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlanDiscountRequest;
use App\Http\Requests\Admin\UpdatePlanDiscountRequest;
use App\Models\PlanDiscount;
use App\Models\SubscriptionPlan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PlanDiscountController extends Controller
{
    public function index(): View
    {
        $discounts = PlanDiscount::query()->with('plan')->latest()->paginate(20);
        $plans = SubscriptionPlan::query()->orderBy('sort_order')->get();

        return view('pages.admin.billing.discounts.index', compact('discounts', 'plans'));
    }

    public function store(StorePlanDiscountRequest $request): RedirectResponse
    {
        PlanDiscount::query()->create($request->validated() + [
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Discount created successfully.');
    }

    public function update(UpdatePlanDiscountRequest $request, PlanDiscount $discount): RedirectResponse
    {
        $discount->update($request->validated() + [
            'is_active' => $request->boolean('is_active', false),
        ]);

        return back()->with('success', 'Discount updated successfully.');
    }

    public function destroy(PlanDiscount $discount): RedirectResponse
    {
        $discount->delete();

        return back()->with('success', 'Discount removed successfully.');
    }
}
