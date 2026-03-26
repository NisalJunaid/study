<?php

namespace App\Services\AI;

use App\Models\Question;

class ModelRoutingService
{
    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function resolve(array $context, bool $escalated = false): array
    {
        $questionType = (string) ($context['question_type'] ?? Question::TYPE_THEORY);

        if (! $this->shouldUseAi($questionType)) {
            return [
                'use_ai' => false,
                'tier' => 'local_only',
                'model' => null,
                'profile' => 'local',
                'temperature' => 0,
                'max_output_tokens' => 0,
            ];
        }

        $isComplex = $this->isComplex($context);

        $tier = $isComplex ? 'high_accuracy' : 'low_cost';
        $profile = $this->promptProfile($questionType, $isComplex);

        if ($escalated) {
            $tier = 'fallback';
            $profile = 'extended_response';
        }

        return [
            'use_ai' => true,
            'tier' => $tier,
            'model' => $this->modelForTier($tier),
            'profile' => $profile,
            'temperature' => 0,
            'max_output_tokens' => $this->maxOutputTokens($tier),
        ];
    }

    public function shouldEscalate(?float $confidence, bool $shouldFlagForReview): bool
    {
        if (! $shouldFlagForReview) {
            return false;
        }

        if ($confidence === null) {
            return true;
        }

        return $confidence < (float) config('openai.escalation_confidence_threshold', 0.45);
    }

    public function shouldUseAi(string $questionType): bool
    {
        $localOnlyTypes = [
            Question::TYPE_MCQ,
            'true_false',
            'matching',
            'ordering',
            'fill_blank',
            'numeric_exact',
        ];

        return ! in_array($questionType, $localOnlyTypes, true);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function isComplex(array $context): bool
    {
        $questionType = (string) ($context['question_type'] ?? Question::TYPE_THEORY);
        $answerLength = mb_strlen(trim((string) ($context['student_answer'] ?? '')));
        $questionLength = mb_strlen(trim((string) ($context['question'] ?? '')));
        $sampleLength = mb_strlen(trim((string) ($context['sample_answer'] ?? '')));
        $strictSemantic = (bool) ($context['strict_semantic'] ?? false);

        if ($questionType === Question::TYPE_STRUCTURED_RESPONSE) {
            return $strictSemantic || $answerLength > 220 || $sampleLength > 300;
        }

        if ($strictSemantic) {
            return true;
        }

        return $answerLength > 420 || $sampleLength > 450 || $questionLength > 320;
    }

    private function promptProfile(string $questionType, bool $isComplex): string
    {
        if ($questionType === Question::TYPE_STRUCTURED_RESPONSE) {
            return $isComplex ? 'structured_part_extended' : 'structured_part_compact';
        }

        return $isComplex ? 'extended_response' : 'short_answer';
    }

    private function modelForTier(string $tier): string
    {
        return match ($tier) {
            'high_accuracy' => (string) config('openai.models.high_accuracy', config('openai.model')),
            'fallback' => (string) config('openai.models.fallback', config('openai.models.high_accuracy', config('openai.model'))),
            default => (string) config('openai.models.low_cost', config('openai.model')),
        };
    }

    private function maxOutputTokens(string $tier): int
    {
        $default = (int) config('openai.max_output_tokens', 1200);

        return match ($tier) {
            'low_cost' => max(300, min(900, $default)),
            'high_accuracy', 'fallback' => max(700, $default),
            default => $default,
        };
    }
}
