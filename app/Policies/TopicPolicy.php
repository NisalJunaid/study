<?php

namespace App\Policies;

use App\Models\Topic;
use App\Models\User;

class TopicPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Topic $topic): bool
    {
        return $user->isAdmin() || $topic->is_active;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Topic $topic): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Topic $topic): bool
    {
        return $user->isAdmin();
    }
}
