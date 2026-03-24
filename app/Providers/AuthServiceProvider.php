<?php

namespace App\Providers;

use App\Models\Import;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use App\Policies\ImportPolicy;
use App\Policies\QuestionPolicy;
use App\Policies\QuizPolicy;
use App\Policies\SubjectPolicy;
use App\Policies\TopicPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Subject::class => SubjectPolicy::class,
        Topic::class => TopicPolicy::class,
        Question::class => QuestionPolicy::class,
        Quiz::class => QuizPolicy::class,
        Import::class => ImportPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Gate::define('access-admin-area', fn (User $user): bool => $user->isAdmin());
        Gate::define('access-student-area', fn (User $user): bool => ! $user->isAdmin());
    }
}
