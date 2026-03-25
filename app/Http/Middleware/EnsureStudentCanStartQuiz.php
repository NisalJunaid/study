<?php

namespace App\Http\Middleware;

use App\Support\OverlayMessage;
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
                ->with('overlay', OverlayMessage::redirect(
                    title: 'Billing access required',
                    message: $access['message'] ?? 'Billing access required before starting another quiz.',
                    redirectUrl: route('student.billing.subscription'),
                    variant: 'warning',
                    overrides: [
                        'primary_label' => 'Choose a Plan',
                    ],
                ));
        }

        $request->attributes->set('quiz_access_context', $access);

        return $next($request);
    }
}
