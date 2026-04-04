<?php

namespace App\Services\Quiz;

use App\Actions\Student\BuildQuizAction;
use App\Models\Quiz;
use App\Models\Subject;
use App\Models\User;
use App\Services\Analytics\StudentProgressAnalyticsService;
use Illuminate\Support\Arr;

class QuizPresetService
{
    public function __construct(
        private readonly StudentProgressAnalyticsService $analyticsService,
        private readonly BuildQuizAction $buildQuizAction,
    ) {
    }

    public function options(): array
    {
        return [
            ['key' => 'quick_revision', 'label' => 'Quick Revision', 'description' => 'Short mixed refresher for rapid revision.'],
            ['key' => 'weak_topics_only', 'label' => 'Weak Topics Only', 'description' => 'Targets your lowest-performing topics from real quiz history.'],
            ['key' => 'mixed_practice', 'label' => 'Mixed Practice', 'description' => 'Balanced MCQ + theory practice across selected subjects.'],
            ['key' => 'exam_style', 'label' => 'Exam Style / Full Practice', 'description' => 'Longer timed-style mixed set for full-practice stamina.'],
            ['key' => 'continue_recommended', 'label' => 'Continue Recommended Practice', 'description' => 'Uses your progress recommendation strategy directly.'],
        ];
    }

    public function resolve(?string $presetKey, User $student): array
    {
        if ($presetKey === null || $presetKey === '') {
            return ['key' => null, 'params' => [], 'notice' => null];
        }

        $resolved = match ($presetKey) {
            'quick_revision' => [
                'mode' => Quiz::MODE_MIXED,
                'question_count' => 10,
                'guided_step' => 4,
            ],
            'mixed_practice' => [
                'mode' => Quiz::MODE_MIXED,
                'question_count' => 25,
                'guided_step' => 4,
            ],
            'exam_style' => [
                'mode' => Quiz::MODE_MIXED,
                'question_count' => 50,
                'guided_step' => 4,
            ],
            'weak_topics_only' => $this->resolveWeakTopicsPreset($student),
            'continue_recommended' => $this->resolveContinueRecommendedPreset($student),
            default => ['params' => [], 'notice' => null],
        };

        $params = $this->normalizeParams($student, $resolved['params'] ?? []);

        $notice = $resolved['notice'] ?? null;
        if (($params['adjustment_notice'] ?? null) !== null) {
            $notice = trim(($notice ? $notice.' ' : '').$params['adjustment_notice']);
            unset($params['adjustment_notice']);
        }

        return [
            'key' => $presetKey,
            'params' => $params,
            'notice' => $notice,
        ];
    }

    private function resolveWeakTopicsPreset(User $student): array
    {
        $analytics = $this->analyticsService->summarize($student);
        $weakTopics = collect($analytics['weak_topics'] ?? []);

        if ($weakTopics->isEmpty()) {
            return [
                'params' => [
                    'mode' => Quiz::MODE_MIXED,
                    'question_count' => 20,
                    'guided_step' => 4,
                ],
                'notice' => 'Weak-topics preset needs previous graded topic data. We switched you to a mixed practice setup.',
            ];
        }

        $subjectIds = $weakTopics->pluck('subject_id')->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        $topicIds = $weakTopics->pluck('topic_id')->map(fn ($id) => (int) $id)->filter()->unique()->take(6)->values()->all();

        return [
            'params' => [
                'multi_subject_mode' => count($subjectIds) > 1 ? 1 : 0,
                'subject_id' => count($subjectIds) === 1 ? $subjectIds[0] : null,
                'subject_ids' => $subjectIds,
                'topic_ids' => $topicIds,
                'mode' => Quiz::MODE_MIXED,
                'question_count' => 20,
                'guided_step' => 4,
            ],
            'notice' => null,
        ];
    }

    private function resolveContinueRecommendedPreset(User $student): array
    {
        $analytics = $this->analyticsService->summarize($student);
        $recommendation = $analytics['recommendations'] ?? [];

        $params = Arr::only((array) ($recommendation['quiz_setup_params'] ?? []), [
            'multi_subject_mode', 'subject_id', 'subject_ids', 'topic_ids', 'mode', 'question_count', 'difficulty', 'guided_step',
        ]);

        return [
            'params' => $params,
            'notice' => null,
        ];
    }

    private function normalizeParams(User $student, array $params): array
    {
        $normalized = $params;
        $subjectIds = collect($params['subject_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique();

        if ((int) ($params['subject_id'] ?? 0) > 0) {
            $subjectIds->push((int) $params['subject_id']);
        }

        $subjectIds = $subjectIds->unique()->values()->all();

        if ($subjectIds === []) {
            $subjectIds = Subject::query()
                ->active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->limit(3)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        if ($subjectIds !== []) {
            $subjects = Subject::query()
                ->active()
                ->whereIn('id', $subjectIds)
                ->get(['id', 'level']);

            $normalized['subject_ids'] = $subjects->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
            $normalized['subject_id'] = count($normalized['subject_ids']) === 1 ? $normalized['subject_ids'][0] : null;
            $normalized['multi_subject_mode'] = count($normalized['subject_ids']) > 1 ? 1 : 0;
            $normalized['levels'] = $subjects->pluck('level')->map(fn ($level) => (string) $level)->unique()->values()->all();
        }

        $normalized['topic_ids'] = collect($normalized['topic_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        $normalized['mode'] = in_array(($normalized['mode'] ?? null), [Quiz::MODE_MCQ, Quiz::MODE_THEORY, Quiz::MODE_MIXED], true)
            ? $normalized['mode']
            : Quiz::MODE_MIXED;
        $normalized['question_count'] = max(1, (int) ($normalized['question_count'] ?? 20));

        $available = $this->buildQuizAction->availableQuestionCount(
            $normalized['subject_ids'] ?? [],
            $normalized['topic_ids'],
            $normalized['mode'],
            $normalized['difficulty'] ?? null
        );

        if ($available < 1 && ($normalized['topic_ids'] !== [])) {
            $normalized['topic_ids'] = [];
            $available = $this->buildQuizAction->availableQuestionCount(
                $normalized['subject_ids'] ?? [],
                [],
                $normalized['mode'],
                $normalized['difficulty'] ?? null
            );
            $normalized['adjustment_notice'] = 'No questions matched the preset topic filters, so topics were reset to include all available topics.';
        }

        if ($available > 0 && $normalized['question_count'] > $available) {
            $normalized['question_count'] = $available;
            $normalized['adjustment_notice'] = trim((($normalized['adjustment_notice'] ?? '').' Question count was reduced to match currently available questions.'));
        }

        return $normalized;
    }
}
