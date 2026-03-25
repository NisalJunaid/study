<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\BillingPlanController;
use App\Http\Controllers\Admin\PlanDiscountController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Admin\SubscriptionPaymentController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\TheoryReviewController;
use App\Http\Controllers\Admin\TopicController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\Student\HistoryController as StudentHistoryController;
use App\Http\Controllers\Student\LevelController as StudentLevelController;
use App\Http\Controllers\Student\ProgressController as StudentProgressController;
use App\Http\Controllers\Student\QuizController as StudentQuizController;
use App\Http\Controllers\Student\BillingController as StudentBillingController;
use App\Http\Controllers\Student\ResultController as StudentResultController;
use App\Http\Controllers\Student\SubjectController as StudentSubjectController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return view('pages.welcome');
    }

    return auth()->user()->isAdmin()
        ? redirect()->route('admin.dashboard')
        : redirect()->route('student.levels.index');
})->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/settings', [SettingsController::class, 'index'])->name('profile.settings');
    Route::delete('/profile', [AccountController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'role:student'])->group(function () {
    Route::get('/dashboard', fn () => redirect()->route('student.levels.index'))->name('student.dashboard');
    Route::get('/levels', [StudentLevelController::class, 'index'])->name('student.levels.index');
    Route::get('/levels/{level}/subjects', [StudentSubjectController::class, 'indexByLevel'])->name('student.levels.subjects.index');
    Route::get('/subjects', fn () => redirect()->route('student.levels.index'))->name('student.subjects.index');

    Route::get('/quiz/create', fn () => redirect()->route('student.quiz.setup'))->name('student.quiz.builder');
    Route::get('/quiz/setup', [StudentQuizController::class, 'create'])->name('student.quiz.setup');
    Route::post('/quiz', [StudentQuizController::class, 'store'])->middleware('quiz.access')->name('student.quiz.store');
    Route::get('/quiz/{quiz}', [StudentQuizController::class, 'show'])->name('student.quiz.take');
    Route::put('/quiz/{quiz}/questions/{quizQuestion}/answer', [StudentQuizController::class, 'saveAnswer'])->name('student.quiz.answer.save');
    Route::post('/quiz/{quiz}/submit', [StudentQuizController::class, 'submit'])->name('student.quiz.submit');
    Route::get('/quiz/{quiz}/results', [StudentQuizController::class, 'results'])->name('student.quiz.results');
    Route::get('/results', [StudentResultController::class, 'index'])->name('student.results.index');

    Route::get('/history', [StudentHistoryController::class, 'index'])->name('student.history.index');
    Route::get('/progress', StudentProgressController::class)->name('student.progress.index');

    Route::get('/billing', [StudentBillingController::class, 'index'])->name('student.billing.index');
    Route::post('/billing/payments', [StudentBillingController::class, 'storePayment'])->name('student.billing.payments.store');
    Route::get('/billing/payments/{payment}/slip', [StudentBillingController::class, 'slip'])->name('student.billing.payments.slip');
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
        Route::resource('imports', ImportController::class)->only(['index', 'store', 'show']);
        Route::post('imports/{import}/confirm', [ImportController::class, 'confirm'])->name('imports.confirm');
        Route::get('/theory-reviews', [TheoryReviewController::class, 'index'])->name('theory-reviews.index');
        Route::get('/theory-reviews/{theoryReview}', [TheoryReviewController::class, 'show'])->name('theory-reviews.show');
        Route::put('/theory-reviews/{theoryReview}', [TheoryReviewController::class, 'update'])->name('theory-reviews.update');

        Route::prefix('billing')->name('billing.')->group(function () {
            Route::resource('plans', BillingPlanController::class)->except('show');
            Route::resource('discounts', PlanDiscountController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::get('payments', [SubscriptionPaymentController::class, 'index'])->name('payments.index');
            Route::post('payments/{payment}/verify', [SubscriptionPaymentController::class, 'verify'])->name('payments.verify');
            Route::post('payments/{payment}/reject', [SubscriptionPaymentController::class, 'reject'])->name('payments.reject');
            Route::get('payments/{payment}/slip', [SubscriptionPaymentController::class, 'slip'])->name('payments.slip');
        });
    });

require __DIR__.'/auth.php';
