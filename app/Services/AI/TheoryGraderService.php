<?php

namespace App\Services\AI;

use App\Support\DTOs\TheoryGradeResult;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Arr;
use RuntimeException;

class TheoryGraderService
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {
    }

    public function grade(array $payload): TheoryGradeResult
    {
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
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => $this->systemPrompt(),
                            ],
                        ],
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => $this->userPrompt($payload),
                            ],
                        ],
                    ],
                ],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'theory_grading_result',
                        'strict' => true,
                        'schema' => $this->schema(),
                    ],
                ],
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

        return $this->normalize(
            decoded: $decoded,
            maxScore: (float) ($payload['max_score'] ?? 0),
            raw: [
                'response' => $json,
                'parsed' => $decoded,
            ],
        );
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

        $matchedPoints = $this->normalizeStringList($decoded['matched_points'] ?? []);
        $missingPoints = $this->normalizeStringList($decoded['missing_points'] ?? []);

        $feedback = trim((string) ($decoded['feedback'] ?? ''));
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

            return $trimmed === '' ? null : $trimmed;
        }, $value)));
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a strict but fair theory answer grader for O'Level students.
Return only JSON that matches the provided schema.
Do not reveal hidden rubric notes in feedback.
Score conservatively, allow partial credit, and keep feedback short and student-friendly.
PROMPT;
    }

    private function userPrompt(array $payload): string
    {
        $keywords = implode(', ', $payload['keywords'] ?? []);
        $acceptablePhrases = implode(', ', $payload['acceptable_phrases'] ?? []);

        return <<<PROMPT
Grade this answer using the provided rubric.

Question:
{$payload['question']}

Student answer:
{$payload['student_answer']}

Sample correct answer:
{$payload['sample_answer']}

Grading notes:
{$payload['grading_notes']}

Keywords:
{$keywords}

Acceptable phrases:
{$acceptablePhrases}

Max score:
{$payload['max_score']}
PROMPT;
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'verdict',
                'score',
                'confidence',
                'matched_points',
                'missing_points',
                'feedback',
                'should_flag_for_review',
            ],
            'properties' => [
                'verdict' => [
                    'type' => 'string',
                    'enum' => ['correct', 'partially_correct', 'incorrect'],
                ],
                'score' => [
                    'type' => 'number',
                ],
                'confidence' => [
                    'type' => 'number',
                ],
                'matched_points' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'missing_points' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'feedback' => [
                    'type' => 'string',
                ],
                'should_flag_for_review' => [
                    'type' => 'boolean',
                ],
            ],
        ];
    }
}
