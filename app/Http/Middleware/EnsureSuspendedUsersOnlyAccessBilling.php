<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuspendedUsersOnlyAccessBilling
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isAdmin()) {
            return $next($request);
        }

        $subscription = $user->subscriptions()->latest()->first();

        if (! $subscription || ! $subscription->isSuspended()) {
            return $next($request);
        }

        if (
            $request->routeIs('student.billing.*')
            || $request->routeIs('logout')
        ) {
            return $next($request);
        }

        return redirect()
            ->route('student.billing.subscription')
            ->with('error', 'Your account is suspended. Please complete payment recovery to continue.');
    }
}
