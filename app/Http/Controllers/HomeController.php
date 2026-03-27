<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $defaultSubjects = [
            Subject::LEVEL_O => [
                ['name' => 'Mathematics', 'color' => '#4f46e5'],
                ['name' => 'English', 'color' => '#0ea5e9'],
                ['name' => 'Physics', 'color' => '#0891b2'],
                ['name' => 'Chemistry', 'color' => '#14b8a6'],
                ['name' => 'Biology', 'color' => '#16a34a'],
                ['name' => 'Geography', 'color' => '#f59e0b'],
            ],
            Subject::LEVEL_A => [
                ['name' => 'Pure Mathematics', 'color' => '#7c3aed'],
                ['name' => 'Further Mathematics', 'color' => '#4338ca'],
                ['name' => 'Physics', 'color' => '#0369a1'],
                ['name' => 'Chemistry', 'color' => '#0d9488'],
                ['name' => 'Economics', 'color' => '#ea580c'],
                ['name' => 'Literature', 'color' => '#db2777'],
            ],
        ];

        $subjectsByLevel = Subject::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'level', 'color'])
            ->groupBy('level')
            ->map(function ($subjects): array {
                return [
                    'count' => $subjects->count(),
                    'subjects' => $subjects
                        ->take(6)
                        ->map(fn (Subject $subject) => [
                            'name' => $subject->name,
                            'color' => Subject::normalizeColor($subject->color),
                        ])
                        ->values()
                        ->all(),
                ];
            });

        foreach ([Subject::LEVEL_O, Subject::LEVEL_A] as $level) {
            if (! $subjectsByLevel->has($level)) {
                $subjectsByLevel[$level] = [
                    'count' => count($defaultSubjects[$level]),
                    'subjects' => $defaultSubjects[$level],
                ];
                continue;
            }

            if ($subjectsByLevel[$level]['subjects'] === []) {
                $subjectsByLevel[$level]['subjects'] = $defaultSubjects[$level];
                $subjectsByLevel[$level]['count'] = max($subjectsByLevel[$level]['count'], count($defaultSubjects[$level]));
            }
        }

        return view('pages.welcome', [
            'subjectsByLevel' => $subjectsByLevel,
        ]);
    }
}
