<?php

use App\Models\Quiz;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('user.{userId}', function (User $user, int $userId): bool {
    return $user->id === $userId;
});

Broadcast::channel('quiz.{quizId}', function (User $user, int $quizId): bool {
    $quiz = Quiz::query()->find($quizId);

    if (! $quiz) {
        return false;
    }

    return $user->isAdmin() || $quiz->user_id === $user->id;
});

Broadcast::channel('import.{importId}', function (User $user): bool {
    return $user->isAdmin();
});

Broadcast::channel('admin.dashboard', function (User $user): bool {
    return $user->isAdmin();
});

Broadcast::channel('admin.questions', function (User $user): bool {
    return $user->isAdmin();
});
