<?php

namespace App\Http\Controllers\Student;

use App\Actions\Student\BuildQuizAction;
use App\Actions\Student\SaveQuizAnswerAction;
use App\Actions\Student\SubmitQuizAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\SaveQuizAnswerRequest;
use App\Http\Requests\Student\StoreQuizRequest;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\Subject;
use App\Services\Billing\QuizAccessService;
use App\Support\OverlayMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class QuizController extends Controller
{
    public function create(QuizAccessService $quizAccessService): View
    {
        $selectedLevels = collect(request()->input('levels', old('levels', [])))
            ->map(fn ($value) => (string) $value)
            ->filter(fn (string $value) => in_array($value, Subject::levels(), true))
            ->unique()
            ->values();

        if ($selectedLevels->isEmpty()) {
            $legacyLevel = request()->string('level')->toString();
            $selectedLevels = in_array($legacyLevel, Subject::levels(), true)
                ? collect([$legacyLevel])
                : collect([Subject::LEVEL_O]);
        }

        $oldMulti = request()->boolean('multi_subject_mode', old('multi_subject_mode', false));

        $subjects = Subject::query()
            ->active()
            ->whereIn('level', $selectedLevels->all())
            ->with([
                'topics' => fn ($query) => $query->active()->orderBy('sort_order')->orderBy('name'),
            ])
            ->withCount([
                'questions as available_questions_count' => fn ($query) => $query->availableForStudents(),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'color', 'level', 'icon']);

        $selectedSubjectIds = collect(old('subject_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $selectedSubjectId = (int) old('subject_id', request()->integer('subject_id'));
        if ($selectedSubjectId > 0 && ! $subjects->contains('id', $selectedSubjectId)) {
            $selectedSubjectId = 0;
        }

        if (! $oldMulti && $selectedSubjectId > 0) {
            $selectedSubjectIds = collect([$selectedSubjectId]);
        }

        $selectedSubjectIds = $selectedSubjectIds
            ->filter(fn (int $id) => $subjects->contains('id', $id))
            ->values();

        return view('pages.student.quiz.builder', [
            'levels' => collect(Subject::levels())->map(fn (string $level) => [
                'value' => $level,
                'label' => Subject::levelLabel($level),
            ])->all(),
            'selectedLevels' => $selectedLevels->all(),
            'selectedSubjectId' => $selectedSubjectId,
            'selectedSubjectIds' => $selectedSubjectIds->all(),
            'subjects' => $subjects,
            'difficulties' => ['easy', 'medium', 'hard'],
            'modes' => [
                Quiz::MODE_MCQ => 'MCQ',
                Quiz::MODE_THEORY => 'Theory',
                Quiz::MODE_MIXED => 'Mixed',
            ],
            'defaultQuestionCount' => 50,
            'multiSubjectMode' => $oldMulti,
            'billingAccess' => $quizAccessService->evaluate((request()->user()), (int) old('question_count', 50)),
        ]);
    }

    public function store(
        StoreQuizRequest $request,
        BuildQuizAction $buildQuizAction,
        QuizAccessService $quizAccessService
    ): RedirectResponse
    {
        $access = $request->attributes->get('quiz_access_context', $quizAccessService->evaluate($request->user(), (int) $request->input('question_count', 1)));

        if (! ($access['allowed'] ?? false)) {
            return redirect()->route('student.billing.subscription')->with('overlay', OverlayMessage::redirect(
                title: 'Unable to start quiz',
                message: $access['message'] ?? 'Billing access required before starting a quiz.',
                redirectUrl: route('student.billing.subscription'),
                variant: 'warning',
                overrides: [
                    'primary_label' => 'Choose a Plan',
                ],
            ));
        }

        try {
            $payload = $request->validated();
            $payload['billing_access_type'] = $access['access_type'] ?? null;
            $payload['subscription_payment_id'] = $access['payment']->id ?? null;

            $quiz = $buildQuizAction->execute($request->user(), $payload);
        } catch (RuntimeException $exception) {
            return back()
                ->withInput()
                ->withErrors([
                    'question_count' => $exception->getMessage(),
                ]);
        }

        $quizAccessService->registerQuizUsage($request->user(), $access);

        return redirect()
            ->route('student.quiz.take', $quiz)
            ->with('overlay', OverlayMessage::make('Quiz ready', $access['message'] ?? 'Quiz created. Start with question 1.', 'success', ['primary_label' => 'Begin']));
    }

    public function show(Quiz $quiz): View
    {
        $this->authorize('view', $quiz);

        $quiz->load([
            'subject:id,name',
            'quizQuestions' => fn ($query) => $query
                ->orderBy('order_no')
                ->with('studentAnswer:id,quiz_question_id,selected_option_id,answer_text,grading_status,updated_at,question_started_at,answered_at,ideal_time_seconds,answer_duration_seconds,answered_on_time'),
        ]);

        return view('pages.student.quiz.take', [
            'quiz' => $quiz,
        ]);
    }

    public function saveAnswer(
        SaveQuizAnswerRequest $request,
        Quiz $quiz,
        QuizQuestion $quizQuestion,
        SaveQuizAnswerAction $saveQuizAnswerAction
    ): JsonResponse {
        $this->authorize('update', $quiz);

        abort_unless($quizQuestion->quiz_id === $quiz->id, 404);

        try {
            $savedAnswer = $saveQuizAnswerAction->execute(
                quiz: $quiz,
                quizQuestion: $quizQuestion,
                studentId: $request->user()->id,
                payload: $request->validated()
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'status' => 'locked',
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'status' => 'saved',
            'saved_at' => optional($savedAnswer->updated_at)->toIso8601String(),
            'grading_status' => $savedAnswer->grading_status,
            'is_locked' => $savedAnswer->answered_at !== null,
        ]);
    }

    public function submit(Quiz $quiz, SubmitQuizAction $submitQuizAction): RedirectResponse
    {
        $this->authorize('update', $quiz);

        $result = $submitQuizAction->execute($quiz, (int) request()->user()->id);

        return redirect()
            ->route('student.quiz.results', $quiz)
            ->with('overlay', OverlayMessage::make('Quiz submitted', $result['message'], 'success', ['primary_label' => 'View Results']));
    }

    public function results(Request $request, Quiz $quiz): View|JsonResponse
    {
        $this->authorize('view', $quiz);

        $quiz->load([
            'subject:id,name,color',
            'quizQuestions' => fn ($query) => $query
                ->orderBy('order_no')
                ->with([
                    'studentAnswer:id,quiz_question_id,selected_option_id,answer_text,is_correct,score,feedback,grading_status,ai_result_json,graded_at,question_started_at,answered_at,ideal_time_seconds,answer_duration_seconds,answered_on_time',
                    'studentAnswer.selectedOption:id,option_key,option_text',
                ]),
        ]);

        $timedAnswers = $quiz->quizQuestions
            ->pluck('studentAnswer')
            ->filter(fn ($answer) => $answer !== null && $answer->answer_duration_seconds !== null);

        $answeredOnTimeCount = $timedAnswers->where('answered_on_time', true)->count();
        $answeredLateCount = max(0, $timedAnswers->count() - $answeredOnTimeCount);

        $timingSummary = [
            'timed_answers' => $timedAnswers->count(),
            'on_time' => $answeredOnTimeCount,
            'late' => $answeredLateCount,
            'on_time_rate' => $timedAnswers->count() > 0
                ? round(($answeredOnTimeCount / $timedAnswers->count()) * 100, 1)
                : null,
        ];

        if ($request->expectsJson() || $request->query('format') === 'json') {
            return response()->json([
                'quiz' => [
                    'id' => $quiz->id,
                    'status' => $quiz->status,
                    'total_awarded_score' => $quiz->total_awarded_score,
                    'total_possible_score' => $quiz->total_possible_score,
                ],
                'answers' => $quiz->quizQuestions
                    ->filter(fn ($quizQuestion) => $quizQuestion->studentAnswer !== null)
                    ->map(fn ($quizQuestion) => [
                        'id' => $quizQuestion->studentAnswer->id,
                        'grading_status' => $quizQuestion->studentAnswer->grading_status,
                        'is_correct' => $quizQuestion->studentAnswer->is_correct,
                        'score' => $quizQuestion->studentAnswer->score,
                        'max_score' => $quizQuestion->max_score,
                        'feedback' => $quizQuestion->studentAnswer->feedback,
                    ])
                    ->values()
                    ->all(),
            ]);
        }

        return view('pages.student.quiz.results', [
            'quiz' => $quiz,
            'timingSummary' => $timingSummary,
        ]);
    }
}
