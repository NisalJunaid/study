<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\OverrideTheoryGradeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OverrideTheoryReviewRequest;
use App\Models\StudentAnswer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TheoryReviewController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->string('status');
        $manualOnly = $request->boolean('manual_only');

        $query = StudentAnswer::query()
            ->with([
                'user:id,name,email',
                'question:id,question_text',
                'quizQuestion:id,quiz_id,order_no,max_score,requires_manual_review',
                'quizQuestion.quiz:id,status,submitted_at',
            ])
            ->whereHas('quizQuestion', fn ($builder) => $builder->whereJsonContains('question_snapshot->type', 'theory'))
            ->orderByDesc('updated_at');

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

        $reviews = $query->paginate(15)->withQueryString();

        return view('pages.admin.theory-reviews.index', [
            'reviews' => $reviews,
            'filters' => [
                'status' => $status,
                'manual_only' => $manualOnly,
            ],
            'statuses' => [
                StudentAnswer::STATUS_PENDING,
                StudentAnswer::STATUS_PROCESSING,
                StudentAnswer::STATUS_GRADED,
                StudentAnswer::STATUS_MANUAL_REVIEW,
                StudentAnswer::STATUS_OVERRIDDEN,
            ],
        ]);
    }

    public function show(StudentAnswer $theoryReview): View
    {
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
        ]);

        abort_unless(($review->quizQuestion->question_snapshot['type'] ?? null) === 'theory', 404);

        return $review;
    }
}
