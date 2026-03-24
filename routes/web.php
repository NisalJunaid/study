<?php

use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\TopicController;
use App\Http\Controllers\Student\QuizController as StudentQuizController;
use App\Http\Controllers\Student\SubjectController as StudentSubjectController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return view('pages.welcome');
    }

    return auth()->user()->isAdmin()
        ? redirect()->route('admin.dashboard')
        : redirect()->route('student.dashboard');
})->name('home');

Route::middleware(['auth', 'role:student'])->group(function () {
    Route::get('/dashboard', fn () => view('pages.student.dashboard'))->name('student.dashboard');
    Route::get('/subjects', [StudentSubjectController::class, 'index'])->name('student.subjects.index');
    Route::get('/subjects/{subject}', [StudentSubjectController::class, 'show'])->name('student.subjects.show');

    Route::get('/quiz/create', [StudentQuizController::class, 'create'])->name('student.quiz.builder');
    Route::post('/quiz', [StudentQuizController::class, 'store'])->name('student.quiz.store');
    Route::get('/quiz/{quiz}', [StudentQuizController::class, 'show'])->name('student.quiz.take');
    Route::put('/quiz/{quiz}/questions/{quizQuestion}/answer', [StudentQuizController::class, 'saveAnswer'])->name('student.quiz.answer.save');
    Route::post('/quiz/{quiz}/submit', [StudentQuizController::class, 'submit'])->name('student.quiz.submit');
    Route::get('/quiz/{quiz}/results', [StudentQuizController::class, 'results'])->name('student.quiz.results');

    Route::get('/history', fn () => view('pages.student.history.index'))->name('student.history.index');
    Route::get('/progress', fn () => view('pages.student.progress.index'))->name('student.progress.index');
});

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:admin'])
    ->group(function () {
        Route::get('/', fn () => view('pages.admin.dashboard'))->name('dashboard');
        Route::resource('subjects', SubjectController::class)->except('show');
        Route::resource('topics', TopicController::class)->except('show');
        Route::resource('questions', QuestionController::class)->except('show');
        Route::patch('questions/{question}/toggle-publish', [QuestionController::class, 'togglePublish'])->name('questions.toggle-publish');
        Route::get('/imports', fn () => view('pages.admin.imports.index'))->name('imports.index');
        Route::get('/theory-reviews', fn () => view('pages.admin.theory-reviews.index'))->name('theory-reviews.index');
    });
