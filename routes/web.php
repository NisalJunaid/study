<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\BillingPlanController;
use App\Http\Controllers\Admin\PlanDiscountController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Admin\DataManagementController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PaymentSettingController;
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

Route::get('/', HomeController::class)->name('home');

Route::middleware(['auth', 'suspension.guard'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/settings', [SettingsController::class, 'index'])->name('profile.settings');
    Route::delete('/profile', [AccountController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'role:student', 'suspension.guard'])->group(function () {
    Route::redirect('/dashboard', '/quiz/setup')->name('student.dashboard');
    Route::get('/levels', [StudentLevelController::class, 'index'])->name('student.levels.index');
    Route::get('/levels/{level}/subjects', [StudentSubjectController::class, 'indexByLevel'])->name('student.levels.subjects.index');
    Route::redirect('/subjects', '/levels')->name('student.subjects.index');

    Route::redirect('/quiz/create', '/quiz/setup')->name('student.quiz.builder');
    Route::get('/quiz/setup', [StudentQuizController::class, 'create'])->name('student.quiz.setup');
    Route::post('/quiz', [StudentQuizController::class, 'store'])->middleware('quiz.access')->name('student.quiz.store');
    Route::get('/quiz/{quiz}', [StudentQuizController::class, 'show'])->middleware('can:view,quiz')->name('student.quiz.take');
    Route::post('/quiz/{quiz}/interact', [StudentQuizController::class, 'interact'])->middleware('can:update,quiz')->name('student.quiz.interact');
    Route::put('/quiz/{quiz}/questions/{quizQuestion}/answer', [StudentQuizController::class, 'saveAnswer'])->middleware('can:update,quiz')->name('student.quiz.answer.save');
    Route::post('/quiz/{quiz}/submit', [StudentQuizController::class, 'submit'])->middleware('can:update,quiz')->name('student.quiz.submit');
    Route::get('/quiz/{quiz}/results', [StudentQuizController::class, 'results'])->middleware('can:view,quiz')->name('student.quiz.results');
    Route::get('/results', [StudentResultController::class, 'index'])->name('student.results.index');

    Route::get('/history', [StudentHistoryController::class, 'index'])->name('student.history.index');
    Route::get('/progress', StudentProgressController::class)->name('student.progress.index');

    Route::redirect('/billing', '/billing/subscription')->name('student.billing.index');
    Route::get('/billing/subscription', [StudentBillingController::class, 'subscription'])->name('student.billing.subscription');
    Route::post('/billing/subscription/select-plan', [StudentBillingController::class, 'selectPlan'])->name('student.billing.subscription.select-plan');
    Route::get('/billing/payment', [StudentBillingController::class, 'payment'])->name('student.billing.payment');
    Route::post('/billing/payments', [StudentBillingController::class, 'storePayment'])->name('student.billing.payments.store');
    Route::get('/billing/payments/{payment}/slip', [StudentBillingController::class, 'slip'])->middleware('can:view,payment')->name('student.billing.payments.slip');
});

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:admin'])
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::resource('subjects', SubjectController::class)->except('show');
        Route::resource('topics', TopicController::class)->except('show');
        Route::resource('questions', QuestionController::class)->except('show');
        Route::patch('questions/{question}/toggle-publish', [QuestionController::class, 'togglePublish'])->name('questions.toggle-publish');
        Route::get('questions/duplicates', [QuestionController::class, 'duplicates'])->name('questions.duplicates');
        Route::post('questions/{question}/dismiss-flag', [QuestionController::class, 'dismissFlag'])->name('questions.dismiss-flag');
        Route::get('data-management', [DataManagementController::class, 'index'])->name('data-management.index');
        Route::post('data-management/wipe', [DataManagementController::class, 'wipe'])->name('data-management.wipe');
        Route::post('subjects/bulk-action', [SubjectController::class, 'bulkAction'])->name('subjects.bulk-action');
        Route::post('topics/bulk-action', [TopicController::class, 'bulkAction'])->name('topics.bulk-action');
        Route::post('questions/bulk-action', [QuestionController::class, 'bulkAction'])->name('questions.bulk-action');
        Route::prefix('imports')->name('imports.')->controller(ImportController::class)->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::post('/questions', 'store')->name('questions.store');
            Route::post('/subjects-json', 'storeSubjectsJson')->name('subjects.store');
            Route::post('/topics-json', 'storeTopicsJson')->name('topics.store');
            Route::post('/subjects-topics-json', 'storeSubjectTopicJson')->name('subjects-topics.store');
            Route::get('/sample/questions', 'sample')->name('questions.sample');
            Route::get('/sample/subjects-json', 'subjectSample')->name('subjects.sample');
            Route::get('/sample/topics-json', 'topicSample')->name('topics.sample');
            Route::get('/sample/subjects-topics-json', 'subjectTopicSample')->name('subjects-topics.sample');
            Route::get('/{import}', 'show')->name('show');
            Route::post('/{import}/confirm', 'confirm')->name('confirm');
        });
        Route::get('/theory-reviews', [TheoryReviewController::class, 'index'])->name('theory-reviews.index');
        Route::get('/theory-reviews/{theoryReview}', [TheoryReviewController::class, 'show'])->name('theory-reviews.show');
        Route::put('/theory-reviews/{theoryReview}', [TheoryReviewController::class, 'update'])->name('theory-reviews.update');

        Route::prefix('billing')->name('billing.')->group(function () {
            Route::resource('plans', BillingPlanController::class)->except('show');
            Route::resource('discounts', PlanDiscountController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::get('settings', [PaymentSettingController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [PaymentSettingController::class, 'update'])->name('settings.update');
            Route::get('payments', [SubscriptionPaymentController::class, 'index'])->name('payments.index');
            Route::post('payments/{payment}/verify', [SubscriptionPaymentController::class, 'verify'])->name('payments.verify');
            Route::post('payments/{payment}/reject', [SubscriptionPaymentController::class, 'reject'])->name('payments.reject');
            Route::get('payments/{payment}/slip', [SubscriptionPaymentController::class, 'slip'])->middleware('can:view,payment')->name('payments.slip');
        });
    });

require __DIR__.'/auth.php';
