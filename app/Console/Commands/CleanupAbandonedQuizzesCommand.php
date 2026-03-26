<?php

namespace App\Console\Commands;

use App\Services\Quiz\QuizSessionCleanupService;
use Illuminate\Console\Command;

class CleanupAbandonedQuizzesCommand extends Command
{
    protected $signature = 'quizzes:cleanup-abandoned {--minutes=20 : Inactivity timeout in minutes}';

    protected $description = 'Delete unsubmitted quiz sessions that have been inactive beyond the timeout';

    public function handle(QuizSessionCleanupService $cleanupService): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $deleted = $cleanupService->cleanupAbandonedSessions(now(), $minutes);

        $this->info("Abandoned quiz cleanup completed. Deleted {$deleted} quiz session(s).");

        return self::SUCCESS;
    }
}
