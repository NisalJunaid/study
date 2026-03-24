<?php

namespace App\Support\DTOs;

class TheoryGradeResult
{
    public function __construct(
        public readonly string $verdict,
        public readonly float $score,
        public readonly float $confidence,
        public readonly array $matchedPoints,
        public readonly array $missingPoints,
        public readonly string $feedback,
        public readonly bool $shouldFlagForReview,
        public readonly array $raw,
    ) {
    }

    public function toArray(): array
    {
        return [
            'verdict' => $this->verdict,
            'score' => $this->score,
            'confidence' => $this->confidence,
            'matched_points' => $this->matchedPoints,
            'missing_points' => $this->missingPoints,
            'feedback' => $this->feedback,
            'should_flag_for_review' => $this->shouldFlagForReview,
        ];
    }
}
