<?php

namespace App\Policies;

use App\Models\Question;
use App\Models\User;

class QuestionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Question $question): bool
    {
        return $user->isAdmin() || $question->is_published;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Question $question): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Question $question): bool
    {
        return $user->isAdmin();
    }

    public function publish(User $user, Question $question): bool
    {
        return $user->isAdmin();
    }
}
