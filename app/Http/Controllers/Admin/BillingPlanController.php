<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSubscriptionPlanRequest;
use App\Http\Requests\Admin\UpdateSubscriptionPlanRequest;
use App\Models\SubscriptionPlan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class BillingPlanController extends Controller
{
    public function index(): View
    {
        $plans = SubscriptionPlan::query()->withCount('subscriptions')->orderBy('sort_order')->paginate(15);

        return view('pages.admin.billing.plans.index', compact('plans'));
    }

    public function create(): View
    {
        return view('pages.admin.billing.plans.create');
    }

    public function store(StoreSubscriptionPlanRequest $request): RedirectResponse
    {
        SubscriptionPlan::query()->create($request->validated() + ['is_active' => $request->boolean('is_active', true)]);

        return redirect()->route('admin.billing.plans.index')->with('success', 'Plan created successfully.');
    }

    public function edit(SubscriptionPlan $plan): View
    {
        return view('pages.admin.billing.plans.edit', compact('plan'));
    }

    public function update(UpdateSubscriptionPlanRequest $request, SubscriptionPlan $plan): RedirectResponse
    {
        $plan->update($request->validated() + ['is_active' => $request->boolean('is_active', false)]);

        return redirect()->route('admin.billing.plans.index')->with('success', 'Plan updated successfully.');
    }

    public function destroy(SubscriptionPlan $plan): RedirectResponse
    {
        $plan->delete();

        return redirect()->route('admin.billing.plans.index')->with('success', 'Plan deleted successfully.');
    }
}
