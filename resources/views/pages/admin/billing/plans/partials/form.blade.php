@php($editing = isset($plan) && $plan)
<div class="grid-2">
    <label class="field"><span>Code</span><input class="field-control" name="code" value="{{ old('code', $plan->code ?? '') }}" required></label>
    <label class="field"><span>Name</span><input class="field-control" name="name" value="{{ old('name', $plan->name ?? '') }}" required></label>
    <label class="field"><span>Type</span>
        <select class="field-control" name="type" required>
            <option value="monthly" @selected(old('type', $plan->type ?? '') === 'monthly')>Monthly</option>
            <option value="annual" @selected(old('type', $plan->type ?? '') === 'annual')>Annual</option>
        </select>
    </label>
    <label class="field"><span>Price</span><input class="field-control" type="number" step="0.01" min="0" name="price" value="{{ old('price', $plan->price ?? '') }}" required></label>
    <label class="field"><span>Currency</span><input class="field-control" name="currency" value="{{ old('currency', $plan->currency ?? 'USD') }}" required></label>
    <label class="field"><span>Cycle days (optional)</span><input class="field-control" type="number" min="1" name="billing_cycle_days" value="{{ old('billing_cycle_days', $plan->billing_cycle_days ?? '') }}"></label>
    <label class="field"><span>Sort order</span><input class="field-control" type="number" min="0" name="sort_order" value="{{ old('sort_order', $plan->sort_order ?? 0) }}"></label>
    <label class="field"><span>Active</span><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan->is_active ?? true))></label>
</div>
<label class="field"><span>Description</span><textarea class="field-control" name="description">{{ old('description', $plan->description ?? '') }}</textarea></label>
@if($errors->any())
    <div class="alert alert-danger">Please fix the highlighted errors.</div>
@endif
