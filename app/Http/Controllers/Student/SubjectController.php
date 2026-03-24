<?php

namespace App\Http\Controllers\Student;

use App\Actions\Student\BuildQuizAction;
use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\Subject;
use Illuminate\Contracts\View\View;

class SubjectController extends Controller
{
    public function index(): View
    {
        $subjects = Subject::query()
            ->active()
            ->withCount([
                'topics as active_topics_count' => fn ($query) => $query->active(),
                'questions as available_questions_count' => fn ($query) => $query->availableForStudents(),
                'questions as mcq_questions_count' => fn ($query) => $query->availableForStudents()->mcq(),
                'questions as theory_questions_count' => fn ($query) => $query->availableForStudents()->theory(),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('pages.student.subjects.index', [
            'subjects' => $subjects,
        ]);
    }

    public function show(Subject $subject, BuildQuizAction $buildQuizAction): View
    {
        abort_unless($subject->is_active, 404);

        $subject->loadCount([
            'questions as available_questions_count' => fn ($query) => $query->availableForStudents(),
            'questions as mcq_questions_count' => fn ($query) => $query->availableForStudents()->mcq(),
            'questions as theory_questions_count' => fn ($query) => $query->availableForStudents()->theory(),
        ]);

        $subject->load([
            'topics' => fn ($query) => $query
                ->active()
                ->withCount([
                    'questions as available_questions_count' => fn ($builder) => $builder->availableForStudents(),
                    'questions as mcq_questions_count' => fn ($builder) => $builder->availableForStudents()->mcq(),
                    'questions as theory_questions_count' => fn ($builder) => $builder->availableForStudents()->theory(),
                ])
                ->orderBy('sort_order')
                ->orderBy('name'),
        ]);

        return view('pages.student.subjects.show', [
            'subject' => $subject,
            'quizModes' => [
                Quiz::MODE_MCQ => [
                    'label' => 'MCQ only',
                    'count' => $buildQuizAction->availableQuestionCount($subject, [], Quiz::MODE_MCQ, null),
                ],
                Quiz::MODE_THEORY => [
                    'label' => 'Theory only',
                    'count' => $buildQuizAction->availableQuestionCount($subject, [], Quiz::MODE_THEORY, null),
                ],
                Quiz::MODE_MIXED => [
                    'label' => 'Mixed mode',
                    'count' => $buildQuizAction->availableQuestionCount($subject, [], Quiz::MODE_MIXED, null),
                ],
            ],
        ]);
    }
}
