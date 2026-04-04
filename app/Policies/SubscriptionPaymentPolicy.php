<?php

namespace App\Policies;

use App\Models\SubscriptionPayment;
use App\Models\User;

class SubscriptionPaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, SubscriptionPayment $payment): bool
    {
        return $user->isAdmin() || $payment->user_id === $user->id;
    }

    public function update(User $user, SubscriptionPayment $payment): bool
    {
        return $user->isAdmin();
    }
}
