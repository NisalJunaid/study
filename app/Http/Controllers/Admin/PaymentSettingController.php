<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePaymentSettingRequest;
use App\Models\PaymentSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PaymentSettingController extends Controller
{
    public function edit(): View
    {
        return view('pages.admin.billing.settings.edit', [
            'setting' => PaymentSetting::current(),
        ]);
    }

    public function update(UpdatePaymentSettingRequest $request): RedirectResponse
    {
        $setting = PaymentSetting::current();
        $setting->update($request->validated());

        return redirect()->route('admin.billing.settings.edit')->with('success', 'Payment destination details updated successfully.');
    }
}
