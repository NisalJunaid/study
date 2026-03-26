<?php

namespace App\Services\Quiz;

use App\Models\Quiz;
use Carbon\CarbonInterface;

class QuizSessionCleanupService
{
    public const DEFAULT_TIMEOUT_MINUTES = 20;

    public function cleanupAbandonedSessions(?CarbonInterface $now = null, int $timeoutMinutes = self::DEFAULT_TIMEOUT_MINUTES): int
    {
        $cutoff = ($now ?? now())->copy()->subMinutes($timeoutMinutes);
        $deleted = 0;

        Quiz::query()
            ->abandonable($cutoff)
            ->select('id')
            ->chunkById(200, function ($quizzes) use (&$deleted): void {
                foreach ($quizzes as $quiz) {
                    $quiz->delete();
                    $deleted++;
                }
            });

        return $deleted;
    }
}
