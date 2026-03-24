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
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class QuizController extends Controller
{
    public function create(): View
    {
        $selectedLevel = request()->string('level')->toString();
        if (! in_array($selectedLevel, Subject::levels(), true)) {
            $selectedLevel = Subject::LEVEL_O;
        }

        $oldMulti = request()->boolean('multi_subject_mode', old('multi_subject_mode', false));

        $subjects = Subject::query()
            ->active()
            ->forLevel($selectedLevel)
            ->with([
                'topics' => fn ($query) => $query->active()->orderBy('sort_order')->orderBy('name'),
            ])
            ->withCount([
                'questions as available_questions_count' => fn ($query) => $query->availableForStudents(),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'color', 'level']);

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
            'selectedLevel' => $selectedLevel,
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
            ->with('success', $result['message']);
    }

    public function results(Quiz $quiz): View
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

        return view('pages.student.quiz.results', [
            'quiz' => $quiz,
            'timingSummary' => [
                'timed_answers' => $timedAnswers->count(),
                'on_time' => $answeredOnTimeCount,
                'late' => $answeredLateCount,
                'on_time_rate' => $timedAnswers->count() > 0
                    ? round(($answeredOnTimeCount / $timedAnswers->count()) * 100, 1)
                    : null,
            ],
        ]);
    }
}
