<?php

namespace App\Services\AI;

use App\Exceptions\TheoryGradingException;
use App\Support\DTOs\TheoryGradeResult;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Str;
use RuntimeException;

class TheoryGraderService
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly ModelRoutingService $modelRoutingService,
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

        $apiKey = (string) config('openai.api_key');

        if ($apiKey === '') {
            throw new TheoryGradingException('OpenAI API key is not configured.', false);
        }

        $resolved = [];
        $uncached = [];

        foreach ($items as $itemKey => $item) {
            $route = $this->modelRoutingService->resolve($item);

            if (! ($route['use_ai'] ?? true)) {
                continue;
            }

            $cacheKey = $this->cacheKey($item, $route);

            if ($this->cacheEnabled()) {
                $cached = Cache::get($cacheKey);

                if (is_array($cached)) {
                    $resolved[$itemKey] = $this->normalize(
                        decoded: $cached,
                        maxScore: (float) ($item['max_score'] ?? 0),
                        raw: [
                            'cached' => true,
                            'parsed' => $cached,
                            'routing' => $route,
                        ],
                    );

                    continue;
                }
            }

            $uncached[$itemKey] = [
                'item_key' => (string) $itemKey,
                'payload' => $item,
                'cache_key' => $cacheKey,
                'route' => $route,
            ];
        }

        if ($uncached === []) {
            return $resolved;
        }

        $groupedByRoute = [];

        foreach ($uncached as $itemKey => $meta) {
            $route = $meta['route'];
            $signature = $this->routeSignature($route);
            $groupedByRoute[$signature]['route'] = $route;
            $groupedByRoute[$signature]['items'][$itemKey] = $meta;
        }

        $escalationCandidates = [];

        foreach ($groupedByRoute as $group) {
            $route = $group['route'];
            $groupItems = $group['items'];

            try {
                $primaryResults = $this->requestBatch($groupItems, $route);

                foreach ($groupItems as $itemKey => $meta) {
                    $returned = $primaryResults[$itemKey] ?? null;

                    if (! is_array($returned)) {
                        if (($route['tier'] ?? null) === 'low_cost') {
                            $escalationCandidates[$itemKey] = $meta;
                            continue;
                        }

                        throw new RuntimeException("OpenAI grading response is missing item '{$itemKey}'.");
                    }

                    $normalized = $this->normalize(
                        decoded: $returned,
                        maxScore: (float) ($meta['payload']['max_score'] ?? 0),
                        raw: [
                            'parsed' => $returned,
                            'routing' => $route,
                            'escalated' => false,
                        ],
                    );

                    if (($route['tier'] ?? null) === 'low_cost' && $this->modelRoutingService->shouldEscalate($normalized->confidence, $normalized->shouldFlagForReview)) {
                        $escalationCandidates[$itemKey] = $meta;
                        continue;
                    }

                    $resolved[$itemKey] = $normalized;

                    if ($this->cacheEnabled()) {
                        Cache::put(
                            $meta['cache_key'],
                            $normalized->toArray(),
                            now()->addSeconds((int) config('openai.cache_ttl_seconds', 60 * 60 * 24 * 30))
                        );
                    }
                }
            } catch (RuntimeException $exception) {
                if (($route['tier'] ?? null) !== 'low_cost') {
                    throw $exception;
                }

                foreach ($groupItems as $itemKey => $meta) {
                    $escalationCandidates[$itemKey] = $meta;
                }
            }
        }

        if ($escalationCandidates !== []) {
            $this->resolveEscalations($escalationCandidates, $resolved);
        }

        return $resolved;
    }

    /**
     * @param  array<string, array{item_key:string,payload:array<string,mixed>,cache_key:string,route:array<string,mixed>}>  $items
     * @return array<string, array<string, mixed>>
     */
    private function requestBatch(array $items, array $route): array
    {
        try {
            $response = $this->http
                ->baseUrl((string) config('openai.base_url'))
                ->timeout((int) config('openai.timeout'))
                ->withToken((string) config('openai.api_key'))
                ->acceptJson()
                ->asJson()
                ->post('/responses', [
                    'model' => (string) ($route['model'] ?? config('openai.model')),
                    'input' => [
                        [
                            'role' => 'system',
                            'content' => [[
                                'type' => 'input_text',
                                'text' => $this->systemPrompt((string) ($route['profile'] ?? 'short_answer')),
                            ]],
                        ],
                        [
                            'role' => 'user',
                            'content' => [[
                                'type' => 'input_text',
                                'text' => $this->userPrompt($items, (string) ($route['profile'] ?? 'short_answer')),
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
                    'max_output_tokens' => (int) ($route['max_output_tokens'] ?? config('openai.max_output_tokens', 1200)),
                    'temperature' => (float) ($route['temperature'] ?? 0),
                ]);
        } catch (ConnectionException $exception) {
            throw new TheoryGradingException('OpenAI grading request could not connect.', true, null);
        }

        if ($response->failed()) {
            $status = $response->status();
            $retriable = in_array($status, [408, 409, 425, 429], true) || $status >= 500;
            throw new TheoryGradingException('OpenAI grading request failed with status '.$status, $retriable, $status);
        }

        $json = $response->json();
        $rawOutputText = Arr::get($json, 'output.0.content.0.text');

        if (! is_string($rawOutputText) || trim($rawOutputText) === '') {
            throw new TheoryGradingException('OpenAI grading response did not include structured JSON text.', false);
        }

        $decoded = json_decode($rawOutputText, true);

        if (! is_array($decoded)) {
            throw new TheoryGradingException('OpenAI grading response JSON is invalid.', false);
        }

        $results = Arr::get($decoded, 'results');

        if (! is_array($results)) {
            throw new TheoryGradingException('OpenAI grading response is missing results.', false);
        }

        Log::info('Theory grading batch completed', [
            'count' => count($items),
            'model' => $route['model'] ?? null,
            'tier' => $route['tier'] ?? null,
            'profile' => $route['profile'] ?? null,
        ]);

        $resultsByItemKey = [];

        foreach ($results as $result) {
            if (! is_array($result) || ! is_string($result['item_key'] ?? null)) {
                continue;
            }

            $resultsByItemKey[(string) $result['item_key']] = $result;
        }

        return $resultsByItemKey;
    }

    /**
     * @param  array<string, array{item_key:string,payload:array<string,mixed>,cache_key:string,route:array<string,mixed>}>  $candidates
     * @param  array<string, TheoryGradeResult>  $resolved
     */
    private function resolveEscalations(array $candidates, array &$resolved): void
    {
        $grouped = [];

        foreach ($candidates as $itemKey => $meta) {
            $route = $this->modelRoutingService->resolve($meta['payload'], true);
            $meta['route'] = $route;
            $signature = $this->routeSignature($route);
            $grouped[$signature]['route'] = $route;
            $grouped[$signature]['items'][$itemKey] = $meta;
        }

        foreach ($grouped as $group) {
            $route = $group['route'];
            $groupItems = $group['items'];

            $results = $this->requestBatch($groupItems, $route);

            foreach ($groupItems as $itemKey => $meta) {
                $returned = $results[$itemKey] ?? null;

                if (! is_array($returned)) {
                    throw new RuntimeException("OpenAI escalation response is missing item '{$itemKey}'.");
                }

                $normalized = $this->normalize(
                    decoded: $returned,
                    maxScore: (float) ($meta['payload']['max_score'] ?? 0),
                    raw: [
                        'parsed' => $returned,
                        'routing' => $route,
                        'escalated' => true,
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
        }
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

    private function systemPrompt(string $profile): string
    {
        return match ($profile) {
            'structured_part_compact' => 'Grade each structured part briefly. Return strict JSON only. Score 0..max_score. Be conservative and concise.',
            'structured_part_extended' => 'Grade each structured part carefully with strict rubric matching. Return strict JSON only. Score 0..max_score and flag uncertainty.',
            'extended_response' => 'Grade O\'Level extended theory answers with nuance and conservative scoring. Return strict JSON only and never expose hidden rubric text.',
            default => 'Grade O\'Level short theory answers. Return strict JSON only. Score 0..max_score with concise feedback.',
        };
    }

    /**
     * @param  array<string, array{item_key:string,payload:array<string,mixed>,cache_key:string,route:array<string,mixed>}>  $items
     */
    private function userPrompt(array $items, string $profile): string
    {
        $inputItems = array_map(function (array $item) use ($profile): array {
            $payload = $item['payload'];

            $base = [
                'item_key' => $item['item_key'],
                'question' => (string) ($payload['question'] ?? ''),
                'student_answer' => (string) ($payload['student_answer'] ?? ''),
                'sample_answer' => (string) ($payload['sample_answer'] ?? ''),
                'max_score' => (float) ($payload['max_score'] ?? 0),
            ];

            if (in_array($profile, ['extended_response', 'structured_part_extended'], true)) {
                $base['grading_notes'] = (string) ($payload['grading_notes'] ?? '');
                $base['keywords'] = array_values(array_filter((array) ($payload['keywords'] ?? []), fn ($value) => is_string($value) && trim($value) !== ''));
                $base['acceptable_phrases'] = array_values(array_filter((array) ($payload['acceptable_phrases'] ?? []), fn ($value) => is_string($value) && trim($value) !== ''));
            }

            return $base;
        }, array_values($items));

        return 'Return {"results":[...]} with one entry per item_key and fields item_key, verdict(correct|partially_correct|incorrect), score, confidence(0..1), matched_points(max3), missing_points(max3), feedback(max220 chars), should_flag_for_review. Items: '.json_encode($inputItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
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
     * @param  array<string, mixed>  $route
     */
    private function cacheKey(array $item, array $route): string
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
            'model' => (string) ($route['model'] ?? config('openai.model')),
            'profile' => (string) ($route['profile'] ?? 'short_answer'),
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

    /**
     * @param  array<string, mixed>  $route
     */
    private function routeSignature(array $route): string
    {
        return implode('|', [
            (string) ($route['model'] ?? ''),
            (string) ($route['profile'] ?? ''),
            (string) ($route['temperature'] ?? ''),
            (string) ($route['max_output_tokens'] ?? ''),
        ]);
    }

    private function cacheEnabled(): bool
    {
        return (bool) config('openai.enable_caching', true);
    }
}
