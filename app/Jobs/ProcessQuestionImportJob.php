<?php

namespace App\Jobs;

use App\Models\Import;
use App\Services\Import\QuestionImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessQuestionImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $importId,
    ) {}

    public function handle(QuestionImportService $questionImportService): void
    {
        $import = Import::query()->with('uploadedBy')->find($this->importId);

        if (! $import || ! $import->uploadedBy) {
            return;
        }

        $questionImportService->processImport($import, $import->uploadedBy);
    }
}
