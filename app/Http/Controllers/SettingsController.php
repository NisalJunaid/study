<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Request $request): View
    {
        return view('pages.account.settings', [
            'layout' => $request->user()?->isAdmin() ? 'layouts.admin' : 'layouts.student',
            'heading' => 'Settings',
            'subheading' => 'Update your password and account security settings.',
            'status' => session('status'),
        ]);
    }
}
