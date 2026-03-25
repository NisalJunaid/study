<?php

namespace Database\Seeders;

use App\Models\McqOption;
use App\Models\Question;
use App\Models\SubscriptionPlan;
use App\Models\Subject;
use App\Models\TheoryQuestionMeta;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StudyHelpDemoSeeder extends Seeder
{
    /**
     * Seed the application's database with demo study-help data.
     */
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@olevel.test'],
            [
                'name' => 'Demo Admin',
                'password' => 'password',
                'role' => User::ROLE_ADMIN,
                'email_verified_at' => now(),
            ]
        );

        $student = User::query()->updateOrCreate(
            ['email' => 'student@olevel.test'],
            [
                'name' => 'Demo Student',
                'password' => 'password',
                'role' => User::ROLE_STUDENT,
                'email_verified_at' => now(),
            ]
        );

        SubscriptionPlan::query()->updateOrCreate(
            ['code' => 'monthly-standard'],
            [
                'name' => 'Monthly Plan',
                'type' => SubscriptionPlan::TYPE_MONTHLY,
                'price' => 9.99,
                'currency' => 'USD',
                'billing_cycle_days' => 30,
                'description' => 'Monthly access with manual payment verification.',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        SubscriptionPlan::query()->updateOrCreate(
            ['code' => 'annual-standard'],
            [
                'name' => 'Annual Plan',
                'type' => SubscriptionPlan::TYPE_ANNUAL,
                'price' => 99.00,
                'currency' => 'USD',
                'billing_cycle_days' => 365,
                'description' => 'Annual one-off payment with 3-day grace after expiry.',
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        $subjects = [
            'Mathematics' => [
                'topics' => ['Algebra', 'Geometry', 'Trigonometry'],
                'color' => '#4f46e5',
            ],
            'English Language' => [
                'topics' => ['Grammar', 'Comprehension', 'Essay Writing'],
                'color' => '#ec4899',
            ],
            'Biology' => [
                'topics' => ['Cells', 'Genetics', 'Ecology'],
                'color' => '#16a34a',
            ],
            'Chemistry' => [
                'topics' => ['Atomic Structure', 'Chemical Reactions', 'Organic Chemistry'],
                'color' => '#f97316',
            ],
            'Physics' => [
                'topics' => ['Mechanics', 'Electricity', 'Waves'],
                'color' => '#0ea5e9',
            ],
        ];

        foreach ($subjects as $subjectName => $subjectMeta) {
            $subject = Subject::query()->updateOrCreate(
                ['slug' => Str::slug($subjectName)],
                [
                    'name' => $subjectName,
                    'level' => Subject::LEVEL_O,
                    'description' => "Core {$subjectName} topics for O'Level prep.",
                    'color' => $subjectMeta['color'],
                    'is_active' => true,
                ]
            );

            foreach ($subjectMeta['topics'] as $index => $topicName) {
                Topic::query()->updateOrCreate(
                    ['subject_id' => $subject->id, 'slug' => Str::slug($topicName)],
                    [
                        'name' => $topicName,
                        'description' => "{$topicName} practice questions.",
                        'is_active' => true,
                        'sort_order' => $index,
                    ]
                );
            }
        }

        $math = Subject::query()->where('slug', 'mathematics')->firstOrFail();
        $algebra = Topic::query()->where('subject_id', $math->id)->where('slug', 'algebra')->firstOrFail();

        $mcqQuestion = Question::query()->updateOrCreate(
            ['subject_id' => $math->id, 'topic_id' => $algebra->id, 'question_text' => 'What is 2x when x = 3?'],
            [
                'type' => Question::TYPE_MCQ,
                'difficulty' => 'easy',
                'marks' => 1,
                'is_published' => true,
                'explanation' => '2 multiplied by 3 equals 6.',
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]
        );

        $options = [
            ['A', '3', false, 1],
            ['B', '5', false, 2],
            ['C', '6', true, 3],
            ['D', '8', false, 4],
        ];

        foreach ($options as [$key, $text, $isCorrect, $sortOrder]) {
            McqOption::query()->updateOrCreate(
                ['question_id' => $mcqQuestion->id, 'option_key' => $key],
                [
                    'option_text' => $text,
                    'is_correct' => $isCorrect,
                    'sort_order' => $sortOrder,
                ]
            );
        }

        $english = Subject::query()->where('slug', 'english-language')->firstOrFail();
        $essay = Topic::query()->where('subject_id', $english->id)->where('slug', 'essay-writing')->firstOrFail();

        $theoryQuestion = Question::query()->updateOrCreate(
            ['subject_id' => $english->id, 'topic_id' => $essay->id, 'question_text' => 'Explain why punctuation is important in writing.'],
            [
                'type' => Question::TYPE_THEORY,
                'difficulty' => 'medium',
                'marks' => 3,
                'is_published' => true,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]
        );

        TheoryQuestionMeta::query()->updateOrCreate(
            ['question_id' => $theoryQuestion->id],
            [
                'sample_answer' => 'Punctuation helps clarify meaning, structure sentences, and guide pauses.',
                'grading_notes' => 'Expect meaning, clarity, and sentence structure.',
                'keywords' => ['clarity', 'meaning', 'structure'],
                'acceptable_phrases' => ['guides pauses', 'separates ideas'],
                'max_score' => 3,
            ]
        );

        // Keep a few students for local testing.
        User::factory()->student()->count(5)->create();

        // Ensure demo student always exists and stays a student role.
        $student->forceFill(['role' => User::ROLE_STUDENT])->save();
    }
}
