<?php

namespace App\Policies;

use App\Models\Quiz;
use App\Models\User;

class QuizPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Quiz $quiz): bool
    {
        return $user->isAdmin() || $quiz->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return ! $user->isAdmin();
    }

    public function update(User $user, Quiz $quiz): bool
    {
        return ! $user->isAdmin() && $quiz->user_id === $user->id;
    }
}
