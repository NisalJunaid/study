<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Contracts\View\View;

class SubjectController extends Controller
{
    public function indexByLevel(string $level): View
    {
        abort_unless(in_array($level, Subject::levels(), true), 404);

        $subjects = Subject::query()
            ->active()
            ->forLevel($level)
            ->withCount([
                'topics as active_topics_count' => fn ($query) => $query->active(),
                'questions as available_questions_count' => fn ($query) => $query->availableForStudents(),
                'questions as mcq_questions_count' => fn ($query) => $query->availableForStudents()->mcq(),
                'questions as theory_questions_count' => fn ($query) => $query->availableForStudents()->theoryLike(),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('pages.student.subjects.index', [
            'level' => $level,
            'levelLabel' => Subject::levelLabel($level),
            'subjects' => $subjects,
        ]);
    }
}
