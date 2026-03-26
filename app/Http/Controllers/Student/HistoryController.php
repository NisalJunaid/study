<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function index(Request $request): View
    {
        $studentId = (int) $request->user()->id;

        $quizzes = Quiz::query()
            ->forUser($studentId)
            ->submittedAttempts()
            ->with('subject:id,name,color')
            ->latest('submitted_at')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('pages.student.history.index', [
            'quizzes' => $quizzes,
        ]);
    }
}
