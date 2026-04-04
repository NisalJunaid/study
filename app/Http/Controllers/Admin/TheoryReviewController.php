<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\OverrideTheoryGradeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OverrideTheoryReviewRequest;
use App\Models\Question;
use App\Models\StudentAnswer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TheoryReviewController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('reviewAny', StudentAnswer::class);

        $status = (string) $request->string('status');
        $manualOnly = $request->boolean('manual_only');
        $queueState = (string) $request->string('queue_state');
        $sort = (string) $request->string('sort', 'updated_desc');

        $baseTheoryAnswers = StudentAnswer::query()
            ->whereHas('quizQuestion', fn ($builder) => $builder->where(function ($query): void {
                $query->whereJsonContains('question_snapshot->type', Question::TYPE_THEORY)
                    ->orWhereJsonContains('question_snapshot->type', Question::TYPE_STRUCTURED_RESPONSE);
            }));

        $query = (clone $baseTheoryAnswers)
            ->with([
                'user:id,name,email',
                'question:id,question_text',
                'quizQuestion:id,quiz_id,order_no,max_score,requires_manual_review',
                'quizQuestion.quiz:id,status,submitted_at',
            ]);

        if ($status !== '') {
            $query->where('grading_status', $status);
        }

        if ($manualOnly) {
            $query->where(function ($builder): void {
                $builder
                    ->where('grading_status', StudentAnswer::STATUS_MANUAL_REVIEW)
                    ->orWhereHas('quizQuestion', fn ($q) => $q->where('requires_manual_review', true));
            });
        }

        if ($queueState !== '') {
            if ($queueState === 'pending_manual_review') {
                $query->where('grading_status', StudentAnswer::STATUS_MANUAL_REVIEW)
                    ->where(function ($builder): void {
                        $builder->whereNull('ai_result_json')
                            ->orWhere('ai_result_json->manual_review_reason', 'pending_manual_review');
                    });
            }

            if ($queueState === 'ai_failed') {
                $query->where('grading_status', StudentAnswer::STATUS_MANUAL_REVIEW)
                    ->where(function ($builder): void {
                        $builder->whereNotNull('ai_result_json->error')
                            ->orWhere('ai_result_json->manual_review_reason', 'ai_failed');
                    });
            }

            if ($queueState === 'low_confidence') {
                $query->where('grading_status', StudentAnswer::STATUS_MANUAL_REVIEW)
                    ->where(function ($builder): void {
                        $builder->where('ai_result_json->manual_review_reason', 'low_confidence')
                            ->orWhere('ai_result_json->parsed->should_flag_for_review', true);
                    });
            }
        }

        if ($sort === 'oldest_outstanding') {
            $query->orderBy('graded_at')->orderBy('updated_at');
        } else {
            $query->orderByDesc('updated_at');
        }

        $reviews = $query->paginate(15)->withQueryString();

        $manualBacklog = (clone $baseTheoryAnswers)
            ->where('grading_status', StudentAnswer::STATUS_MANUAL_REVIEW);

        $summary = [
            'waiting_manual_review' => (clone $manualBacklog)->count(),
            'ai_failed' => (clone $manualBacklog)
                ->where(function ($builder): void {
                    $builder->where('ai_result_json->manual_review_reason', 'ai_failed')
                        ->orWhereNotNull('ai_result_json->error');
                })
                ->count(),
            'oldest_outstanding_at' => (clone $manualBacklog)->min('graded_at'),
        ];

        return view('pages.admin.theory-reviews.index', [
            'reviews' => $reviews,
            'summary' => $summary,
            'filters' => [
                'status' => $status,
                'manual_only' => $manualOnly,
                'queue_state' => $queueState,
                'sort' => $sort,
            ],
            'statuses' => [
                StudentAnswer::STATUS_PENDING,
                StudentAnswer::STATUS_PROCESSING,
                StudentAnswer::STATUS_GRADED,
                StudentAnswer::STATUS_MANUAL_REVIEW,
                StudentAnswer::STATUS_OVERRIDDEN,
            ],
            'queueStates' => [
                'pending_manual_review' => 'Pending manual review',
                'ai_failed' => 'AI failed',
                'low_confidence' => 'Low confidence',
            ],
        ]);
    }

    public function show(StudentAnswer $theoryReview): View
    {
        $this->authorize('review', $theoryReview);

        $review = $this->loadTheoryReview($theoryReview);

        return view('pages.admin.theory-reviews.show', [
            'review' => $review,
            'aiParsed' => data_get($review->ai_result_json, 'parsed', []),
        ]);
    }

    public function update(
        OverrideTheoryReviewRequest $request,
        StudentAnswer $theoryReview,
        OverrideTheoryGradeAction $overrideTheoryGradeAction
    ): RedirectResponse {
        $this->authorize('override', $theoryReview);

        $review = $this->loadTheoryReview($theoryReview);

        $overrideTheoryGradeAction->execute(
            answer: $review,
            payload: $request->validated(),
            admin: $request->user(),
        );

        return redirect()
            ->route('admin.theory-reviews.show', $review)
            ->with('success', 'Theory grade override saved.');
    }

    private function loadTheoryReview(StudentAnswer $review): StudentAnswer
    {
        $review->load([
            'user:id,name,email',
            'grader:id,name,email',
            'question:id,question_text',
            'quizQuestion:id,quiz_id,question_snapshot,max_score,requires_manual_review,order_no',
            'quizQuestion.quiz:id,user_id,status,total_possible_score,total_awarded_score,submitted_at,graded_at',
            'gradingAttempts' => fn ($query) => $query->latest('id')->limit(10),
        ]);

        abort_unless(in_array(($review->quizQuestion->question_snapshot['type'] ?? null), Question::theoryLikeTypes(), true), 404);

        return $review;
    }
}
