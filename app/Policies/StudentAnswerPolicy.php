<?php

namespace App\Policies;

use App\Models\StudentAnswer;
use App\Models\User;

class StudentAnswerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, StudentAnswer $studentAnswer): bool
    {
        return $user->isAdmin() || $studentAnswer->user_id === $user->id;
    }

    public function reviewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function review(User $user, StudentAnswer $studentAnswer): bool
    {
        return $user->isAdmin();
    }

    public function override(User $user, StudentAnswer $studentAnswer): bool
    {
        return $user->isAdmin();
    }
}
