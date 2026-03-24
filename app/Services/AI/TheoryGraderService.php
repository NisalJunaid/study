<?php

namespace App\Services\AI;

use App\Support\DTOs\TheoryGradeResult;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

class TheoryGraderService
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {
    }

    /**
     * @param  array<string, array<string, mixed>>  $items
     * @return array<string, TheoryGradeResult>
     */
    public function gradeBatch(array $items): array
    {
        if ($items === []) {
            return [];
        }

        $resolved = [];
        $uncached = [];

        foreach ($items as $itemKey => $item) {
            $cacheKey = $this->cacheKey($item);

            if ($this->cacheEnabled()) {
                $cached = Cache::get($cacheKey);

                if (is_array($cached)) {
                    $resolved[$itemKey] = $this->normalize(
                        decoded: $cached,
                        maxScore: (float) ($item['max_score'] ?? 0),
                        raw: ['cached' => true, 'parsed' => $cached]
                    );

                    continue;
                }
            }

            $uncached[$itemKey] = [
                'item_key' => (string) $itemKey,
                'payload' => $item,
                'cache_key' => $cacheKey,
            ];
        }

        if ($uncached === []) {
            return $resolved;
        }

        $apiKey = (string) config('openai.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        $response = $this->http
            ->baseUrl((string) config('openai.base_url'))
            ->timeout((int) config('openai.timeout'))
            ->withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->post('/responses', [
                'model' => config('openai.model'),
                'input' => [
                    [
                        'role' => 'system',
                        'content' => [[
                            'type' => 'input_text',
                            'text' => $this->systemPrompt(),
                        ]],
                    ],
                    [
                        'role' => 'user',
                        'content' => [[
                            'type' => 'input_text',
                            'text' => $this->userPrompt($uncached),
                        ]],
                    ],
                ],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'theory_grading_batch_result',
                        'strict' => true,
                        'schema' => $this->schema(),
                    ],
                ],
                'max_output_tokens' => (int) config('openai.max_output_tokens', 1200),
                'temperature' => 0,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI grading request failed with status '.$response->status());
        }

        $json = $response->json();
        $rawOutputText = Arr::get($json, 'output.0.content.0.text');

        if (! is_string($rawOutputText) || trim($rawOutputText) === '') {
            throw new RuntimeException('OpenAI grading response did not include structured JSON text.');
        }

        $decoded = json_decode($rawOutputText, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI grading response JSON is invalid.');
        }

        $results = Arr::get($decoded, 'results');

        if (! is_array($results)) {
            throw new RuntimeException('OpenAI grading response is missing results.');
        }

        $resultsByItemKey = [];

        foreach ($results as $result) {
            if (! is_array($result) || ! is_string($result['item_key'] ?? null)) {
                continue;
            }

            $resultsByItemKey[(string) $result['item_key']] = $result;
        }

        foreach ($uncached as $itemKey => $meta) {
            $returned = $resultsByItemKey[$itemKey] ?? null;

            if (! is_array($returned)) {
                throw new RuntimeException("OpenAI grading response is missing item '{$itemKey}'.");
            }

            $normalized = $this->normalize(
                decoded: $returned,
                maxScore: (float) ($meta['payload']['max_score'] ?? 0),
                raw: [
                    'response' => $json,
                    'parsed' => $returned,
                    'batch_item_key' => $itemKey,
                ],
            );

            $resolved[$itemKey] = $normalized;

            if ($this->cacheEnabled()) {
                Cache::put(
                    $meta['cache_key'],
                    $normalized->toArray(),
                    now()->addSeconds((int) config('openai.cache_ttl_seconds', 60 * 60 * 24 * 30))
                );
            }
        }

        return $resolved;
    }

    private function normalize(array $decoded, float $maxScore, array $raw): TheoryGradeResult
    {
        $verdict = (string) ($decoded['verdict'] ?? 'incorrect');
        $allowedVerdicts = ['correct', 'partially_correct', 'incorrect'];

        if (! in_array($verdict, $allowedVerdicts, true)) {
            throw new RuntimeException('OpenAI grading verdict is invalid.');
        }

        $score = max(0, min((float) ($decoded['score'] ?? 0), $maxScore));
        $confidence = max(0, min((float) ($decoded['confidence'] ?? 0), 1));

        $matchedPoints = array_slice($this->normalizeStringList($decoded['matched_points'] ?? []), 0, 3);
        $missingPoints = array_slice($this->normalizeStringList($decoded['missing_points'] ?? []), 0, 3);

        $feedback = trim((string) ($decoded['feedback'] ?? ''));
        $feedback = Str::limit($feedback, (int) config('openai.max_feedback_chars', 220), '');

        $shouldFlagForReview = (bool) ($decoded['should_flag_for_review'] ?? false);

        if ($confidence < (float) config('openai.confidence_manual_review_threshold', 0.55)) {
            $shouldFlagForReview = true;
        }

        return new TheoryGradeResult(
            verdict: $verdict,
            score: $score,
            confidence: $confidence,
            matchedPoints: $matchedPoints,
            missingPoints: $missingPoints,
            feedback: $feedback,
            shouldFlagForReview: $shouldFlagForReview,
            raw: $raw,
        );
    }

    private function normalizeStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($item): ?string {
            if (! is_string($item)) {
                return null;
            }

            $trimmed = trim($item);

            return $trimmed === '' ? null : Str::limit($trimmed, 80, '');
        }, $value)));
    }

    private function systemPrompt(): string
    {
        return 'Grade O\'Level theory answers. Return strict JSON only. Score 0..max_score with conservative partial credit. Keep feedback brief. Never include hidden rubric text.';
    }

    /**
     * @param  array<string, array{item_key:string,payload:array<string,mixed>,cache_key:string}>  $items
     */
    private function userPrompt(array $items): string
    {
        $inputItems = array_map(function (array $item): array {
            $payload = $item['payload'];

            return [
                'item_key' => $item['item_key'],
                'question' => (string) ($payload['question'] ?? ''),
                'student_answer' => (string) ($payload['student_answer'] ?? ''),
                'sample_answer' => (string) ($payload['sample_answer'] ?? ''),
                'grading_notes' => (string) ($payload['grading_notes'] ?? ''),
                'keywords' => array_values(array_filter((array) ($payload['keywords'] ?? []), fn ($value) => is_string($value) && trim($value) !== '')),
                'acceptable_phrases' => array_values(array_filter((array) ($payload['acceptable_phrases'] ?? []), fn ($value) => is_string($value) && trim($value) !== '')),
                'max_score' => (float) ($payload['max_score'] ?? 0),
            ];
        }, array_values($items));

        return 'Return {"results": [...]} with one result per item_key. Each result must contain: item_key, verdict(correct|partially_correct|incorrect), score, confidence(0..1), matched_points(max 3), missing_points(max 3), feedback(max 220 chars), should_flag_for_review. Input items: '.json_encode($inputItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['results'],
            'properties' => [
                'results' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => [
                            'item_key',
                            'verdict',
                            'score',
                            'confidence',
                            'matched_points',
                            'missing_points',
                            'feedback',
                            'should_flag_for_review',
                        ],
                        'properties' => [
                            'item_key' => ['type' => 'string'],
                            'verdict' => [
                                'type' => 'string',
                                'enum' => ['correct', 'partially_correct', 'incorrect'],
                            ],
                            'score' => ['type' => 'number'],
                            'confidence' => ['type' => 'number'],
                            'matched_points' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                            ],
                            'missing_points' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                            ],
                            'feedback' => ['type' => 'string'],
                            'should_flag_for_review' => ['type' => 'boolean'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function cacheKey(array $item): string
    {
        $normalize = function (mixed $value): string {
            if (is_array($value)) {
                $value = implode('|', array_map(fn ($entry) => is_scalar($entry) ? (string) $entry : '', $value));
            }

            $text = mb_strtolower(trim((string) $value));
            $text = preg_replace('/\s+/', ' ', $text) ?? $text;

            return $text;
        };

        $signature = [
            'v' => (string) config('openai.cache_version', 'theory-v2'),
            'model' => (string) config('openai.model'),
            'question' => $normalize($item['question'] ?? ''),
            'student_answer' => $normalize($item['student_answer'] ?? ''),
            'sample_answer' => $normalize($item['sample_answer'] ?? ''),
            'grading_notes' => $normalize($item['grading_notes'] ?? ''),
            'keywords' => $normalize($item['keywords'] ?? []),
            'acceptable_phrases' => $normalize($item['acceptable_phrases'] ?? []),
            'max_score' => (float) ($item['max_score'] ?? 0),
        ];

        return 'openai:theory:'.sha1(json_encode($signature, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function cacheEnabled(): bool
    {
        return (bool) config('openai.enable_caching', true);
    }
}
