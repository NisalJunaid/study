<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\BillingPlanController;
use App\Http\Controllers\Admin\PlanDiscountController;
use App\Http\Controllers\Admin\QuestionController;
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
use App\Models\Subject;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
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
})->name('home');

Route::middleware(['auth', 'suspension.guard'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/settings', [SettingsController::class, 'index'])->name('profile.settings');
    Route::delete('/profile', [AccountController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'role:student', 'suspension.guard'])->group(function () {
    Route::get('/dashboard', fn () => redirect()->route('student.quiz.setup'))->name('student.dashboard');
    Route::get('/levels', [StudentLevelController::class, 'index'])->name('student.levels.index');
    Route::get('/levels/{level}/subjects', [StudentSubjectController::class, 'indexByLevel'])->name('student.levels.subjects.index');
    Route::get('/subjects', fn () => redirect()->route('student.levels.index'))->name('student.subjects.index');

    Route::get('/quiz/create', fn () => redirect()->route('student.quiz.setup'))->name('student.quiz.builder');
    Route::get('/quiz/setup', [StudentQuizController::class, 'create'])->name('student.quiz.setup');
    Route::post('/quiz', [StudentQuizController::class, 'store'])->middleware('quiz.access')->name('student.quiz.store');
    Route::get('/quiz/{quiz}', [StudentQuizController::class, 'show'])->name('student.quiz.take');
    Route::post('/quiz/{quiz}/interact', [StudentQuizController::class, 'interact'])->name('student.quiz.interact');
    Route::put('/quiz/{quiz}/questions/{quizQuestion}/answer', [StudentQuizController::class, 'saveAnswer'])->name('student.quiz.answer.save');
    Route::post('/quiz/{quiz}/submit', [StudentQuizController::class, 'submit'])->name('student.quiz.submit');
    Route::get('/quiz/{quiz}/results', [StudentQuizController::class, 'results'])->name('student.quiz.results');
    Route::get('/results', [StudentResultController::class, 'index'])->name('student.results.index');

    Route::get('/history', [StudentHistoryController::class, 'index'])->name('student.history.index');
    Route::get('/progress', StudentProgressController::class)->name('student.progress.index');

    Route::get('/billing', fn () => redirect()->route('student.billing.subscription'))->name('student.billing.index');
    Route::get('/billing/subscription', [StudentBillingController::class, 'subscription'])->name('student.billing.subscription');
    Route::post('/billing/subscription/select-plan', [StudentBillingController::class, 'selectPlan'])->name('student.billing.subscription.select-plan');
    Route::get('/billing/payment', [StudentBillingController::class, 'payment'])->name('student.billing.payment');
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
        Route::resource('imports', ImportController::class)->only(['index', 'show']);
        Route::post('imports/questions', [ImportController::class, 'store'])->name('imports.questions.store');
        Route::post('imports/subjects-json', [ImportController::class, 'storeSubjectsJson'])->name('imports.subjects.store');
        Route::post('imports/topics-json', [ImportController::class, 'storeTopicsJson'])->name('imports.topics.store');
        Route::post('imports/{import}/confirm', [ImportController::class, 'confirm'])->name('imports.confirm');
        Route::get('imports/sample/questions', [ImportController::class, 'sample'])->name('imports.questions.sample');
        Route::get('imports/sample/subjects-json', [ImportController::class, 'subjectSample'])->name('imports.subjects.sample');
        Route::get('imports/sample/topics-json', [ImportController::class, 'topicSample'])->name('imports.topics.sample');
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
            Route::get('payments/{payment}/slip', [SubscriptionPaymentController::class, 'slip'])->name('payments.slip');
        });
    });

require __DIR__.'/auth.php';
