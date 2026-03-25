<?php

namespace App\Http\Middleware;

use App\Services\Billing\QuizAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudentCanStartQuiz
{
    public function __construct(private readonly QuizAccessService $quizAccessService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isAdmin()) {
            return $next($request);
        }

        $questionCount = (int) $request->input('question_count', 1);
        $access = $this->quizAccessService->evaluate($user, $questionCount);

        if (! ($access['allowed'] ?? false)) {
            return redirect()
                ->route('student.billing.subscription')
                ->with('error', $access['message'] ?? 'Billing access required before starting another quiz.');
        }

        $request->attributes->set('quiz_access_context', $access);

        return $next($request);
    }
}
