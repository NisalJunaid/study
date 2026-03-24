<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Contracts\View\View;

class LevelController extends Controller
{
    public function index(): View
    {
        $selectedLevels = collect(request()->input('levels', [Subject::LEVEL_O]))
            ->map(fn ($value) => (string) $value)
            ->filter(fn (string $value) => in_array($value, Subject::levels(), true))
            ->unique()
            ->values();

        if ($selectedLevels->isEmpty()) {
            $selectedLevels = collect([Subject::LEVEL_O]);
        }

        $levels = collect(Subject::levels())
            ->map(function (string $level): array {
                $subjectsQuery = Subject::query()
                    ->active()
                    ->forLevel($level);

                return [
                    'value' => $level,
                    'label' => Subject::levelLabel($level),
                    'subjects_count' => (clone $subjectsQuery)->count(),
                    'questions_count' => (clone $subjectsQuery)
                        ->withCount([
                            'questions as available_questions_count' => fn ($query) => $query->availableForStudents(),
                        ])
                        ->get()
                        ->sum('available_questions_count'),
                ];
            })
            ->all();

        return view('pages.student.levels.index', [
            'levels' => $levels,
            'selectedLevels' => $selectedLevels->all(),
            'multiLevelMode' => count($selectedLevels) > 1,
        ]);
    }
}
