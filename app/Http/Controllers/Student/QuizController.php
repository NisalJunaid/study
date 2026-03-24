<?php

namespace App\Http\Controllers\Student;

use App\Actions\Student\BuildQuizAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreQuizRequest;
use App\Models\Quiz;
use App\Models\Subject;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class QuizController extends Controller
{
    public function create(): View
    {
        $subjects = Subject::query()
            ->active()
            ->with([
                'topics' => fn ($query) => $query->active()->orderBy('sort_order')->orderBy('name'),
            ])
            ->withCount([
                'questions as available_questions_count' => fn ($query) => $query->availableForStudents(),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'color']);

        return view('pages.student.quiz.builder', [
            'subjects' => $subjects,
            'difficulties' => ['easy', 'medium', 'hard'],
            'modes' => [
                Quiz::MODE_MCQ => 'MCQ',
                Quiz::MODE_THEORY => 'Theory',
                Quiz::MODE_MIXED => 'Mixed',
            ],
        ]);
    }

    public function store(StoreQuizRequest $request, BuildQuizAction $buildQuizAction): RedirectResponse
    {
        try {
            $quiz = $buildQuizAction->execute($request->user(), $request->validated());
        } catch (RuntimeException $exception) {
            return back()
                ->withInput()
                ->withErrors([
                    'question_count' => $exception->getMessage(),
                ]);
        }

        return redirect()
            ->route('student.quiz.take', $quiz)
            ->with('success', 'Quiz created. Start with question 1.');
    }

    public function show(Quiz $quiz): View
    {
        $this->authorize('view', $quiz);

        $quiz->load([
            'subject:id,name',
            'quizQuestions' => fn ($query) => $query->orderBy('order_no'),
        ]);

        return view('pages.student.quiz.take', [
            'quiz' => $quiz,
        ]);
    }
}
