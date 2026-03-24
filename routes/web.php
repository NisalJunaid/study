<?php

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
    Route::get('/subjects', fn () => view('pages.student.subjects.index'))->name('student.subjects.index');
    Route::get('/quiz/create', fn () => view('pages.student.quiz.builder'))->name('student.quiz.builder');
    Route::get('/history', fn () => view('pages.student.history.index'))->name('student.history.index');
    Route::get('/progress', fn () => view('pages.student.progress.index'))->name('student.progress.index');
});

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:admin'])
    ->group(function () {
        Route::get('/', fn () => view('pages.admin.dashboard'))->name('dashboard');
        Route::get('/subjects', fn () => view('pages.admin.subjects.index'))->name('subjects.index');
        Route::get('/topics', fn () => view('pages.admin.topics.index'))->name('topics.index');
        Route::get('/questions', fn () => view('pages.admin.questions.index'))->name('questions.index');
        Route::get('/imports', fn () => view('pages.admin.imports.index'))->name('imports.index');
        Route::get('/theory-reviews', fn () => view('pages.admin.theory-reviews.index'))->name('theory-reviews.index');
    });
