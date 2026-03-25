<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'account_path' => ['required', 'in:'.User::ONBOARDING_FREE_TRIAL.','.User::ONBOARDING_SUBSCRIBE],
        ]);

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->string('email')->lower()->value(),
                'password' => Hash::make($request->password),
                'role' => User::ROLE_STUDENT,
                'onboarding_intent' => $request->string('account_path')->toString(),
            ]);
        } catch (QueryException $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'email' => 'We could not create your account right now. Please try again shortly.',
            ]);
        }

        event(new Registered($user));

        Auth::login($user);

        if ($user->onboarding_intent === User::ONBOARDING_SUBSCRIBE) {
            return redirect()->route('student.billing.subscription');
        }

        return redirect()->route('student.dashboard');
    }
}
