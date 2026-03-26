<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use Illuminate\Http\RedirectResponse;

class ResultController extends Controller
{
    public function index(): RedirectResponse
    {
        $latest = Quiz::query()
            ->forUser((int) request()->user()->id)
            ->submittedAttempts()
            ->latest('submitted_at')
            ->latest('id')
            ->first();

        if (! $latest) {
            return redirect()->route('student.history.index');
        }

        return redirect()->route('student.quiz.results', $latest);
    }
}
