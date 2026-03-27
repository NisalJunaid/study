<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConfirmQuestionImportRequest;
use App\Http\Requests\Admin\StoreQuestionImportRequest;
use App\Http\Requests\Admin\StoreSubjectJsonImportRequest;
use App\Http\Requests\Admin\StoreSubjectTopicJsonImportRequest;
use App\Http\Requests\Admin\StoreTopicJsonImportRequest;
use App\Events\ImportProgressUpdated;
use App\Jobs\ProcessQuestionImportJob;
use App\Models\Import;
use App\Services\Import\CurriculumJsonImportService;
use App\Services\Import\QuestionImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    public function index(Request $request, QuestionImportService $questionImportService, CurriculumJsonImportService $curriculumJsonImportService): View
    {
        $this->authorize('viewAny', Import::class);

        $imports = Import::query()
            ->with('uploadedBy:id,name')
            ->latest('id')
            ->paginate(12);

        return view('pages.admin.imports.index', [
            'imports' => $imports,
            'jsonSamples' => $questionImportService->sampleJsonStrings(),
            'subjectJsonSample' => $curriculumJsonImportService->subjectSampleJsonString(),
            'topicJsonSample' => $curriculumJsonImportService->topicSampleJsonString(),
            'subjectTopicJsonSample' => $curriculumJsonImportService->combinedSubjectTopicSampleJsonString(),
        ]);
    }

    public function store(StoreQuestionImportRequest $request, QuestionImportService $questionImportService): RedirectResponse
    {
        /** @var UploadedFile $file */
        $file = $request->importFile();
        $import = $questionImportService->createImportFromUpload(
            $file,
            $request->user(),
            $request->boolean('allow_create_subjects'),
            $request->boolean('allow_create_topics'),
        );

        $format = strtolower((string) pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION)) === 'json' ? 'JSON' : 'CSV';

        return redirect()
            ->route('admin.imports.show', $import)
            ->with('success', "{$format} uploaded and validated. Review the preview before importing.");
    }


    public function storeSubjectsJson(StoreSubjectJsonImportRequest $request, CurriculumJsonImportService $curriculumJsonImportService): RedirectResponse
    {
        $result = $curriculumJsonImportService->importSubjects($request->importFile());

        return redirect()
            ->route('admin.imports.index')
            ->with('success', "Subjects JSON imported successfully. Levels: {$result['levels']['created']} created, {$result['levels']['skipped']} skipped. Subjects: {$result['subjects']['created']} created, {$result['subjects']['skipped']} skipped, {$result['subjects']['failed']} failed.");
    }

    public function storeTopicsJson(StoreTopicJsonImportRequest $request, CurriculumJsonImportService $curriculumJsonImportService): RedirectResponse
    {
        $result = $curriculumJsonImportService->importTopics($request->importFile());

        return redirect()
            ->route('admin.imports.index')
            ->with('success', "Topics JSON imported successfully. {$result['topics']['created']} created, {$result['topics']['skipped']} skipped, {$result['topics']['failed']} failed.");
    }

    public function storeSubjectTopicJson(StoreSubjectTopicJsonImportRequest $request, CurriculumJsonImportService $curriculumJsonImportService): RedirectResponse
    {
        $result = $curriculumJsonImportService->importSubjectsAndTopics($request->importFile());

        return redirect()
            ->route('admin.imports.index')
            ->with(
                'success',
                "Subjects + topics JSON imported successfully. Levels: {$result['levels']['created']} created, {$result['levels']['skipped']} skipped. Subjects: {$result['subjects']['created']} created, {$result['subjects']['skipped']} skipped, {$result['subjects']['failed']} failed. Topics: {$result['topics']['created']} created, {$result['topics']['skipped']} skipped, {$result['topics']['failed']} failed."
            );
    }

    public function subjectSample(CurriculumJsonImportService $curriculumJsonImportService): StreamedResponse
    {
        $this->authorize('viewAny', Import::class);

        $payload = $curriculumJsonImportService->subjectSamplePayload();

        return response()->streamDownload(function () use ($payload): void {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
        }, 'subject-import-sample.json', [
            'Content-Type' => 'application/json',
        ]);
    }

    public function topicSample(CurriculumJsonImportService $curriculumJsonImportService): StreamedResponse
    {
        $this->authorize('viewAny', Import::class);

        $payload = $curriculumJsonImportService->topicSamplePayload();

        return response()->streamDownload(function () use ($payload): void {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
        }, 'topic-import-sample.json', [
            'Content-Type' => 'application/json',
        ]);
    }

    public function subjectTopicSample(CurriculumJsonImportService $curriculumJsonImportService): StreamedResponse
    {
        $this->authorize('viewAny', Import::class);

        $payload = $curriculumJsonImportService->combinedSubjectTopicSamplePayload();

        return response()->streamDownload(function () use ($payload): void {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
        }, 'subject-topic-import-sample.json', [
            'Content-Type' => 'application/json',
        ]);
    }

    public function show(Import $import): View
    {
        $this->authorize('view', $import);

        $rows = $import->importRows()
            ->orderBy('row_number')
            ->paginate(25)
            ->withQueryString();

        return view('pages.admin.imports.show', [
            'import' => $import->load('uploadedBy:id,name'),
            'rows' => $rows,
            'statusLabels' => [
                Import::STATUS_UPLOADED => 'Uploaded',
                Import::STATUS_VALIDATING => 'Validating',
                Import::STATUS_READY => 'Ready',
                Import::STATUS_IMPORTING => 'Importing',
                Import::STATUS_COMPLETED => 'Completed',
                Import::STATUS_PARTIALLY_COMPLETED => 'Partially completed',
                Import::STATUS_FAILED => 'Failed',
            ],
        ]);
    }

    public function confirm(ConfirmQuestionImportRequest $request, Import $import): RedirectResponse
    {
        $this->authorize('create', Import::class);

        if ($import->status !== Import::STATUS_READY) {
            return redirect()
                ->route('admin.imports.show', $import)
                ->with('error', 'This import is not in ready state.');
        }

        $import->forceFill([
            'status' => Import::STATUS_IMPORTING,
            'imported_rows' => 0,
            'failed_rows' => max(0, $import->total_rows - $import->valid_rows),
        ])->save();
        ImportProgressUpdated::dispatch($import->id);

        ProcessQuestionImportJob::dispatch($import->id)
            ->onQueue(config('study.queues.imports'))
            ->afterCommit();

        return redirect()
            ->route('admin.imports.show', $import)
            ->with('success', 'Import started. This page updates live as progress events arrive.');
    }

    public function sample(Request $request, QuestionImportService $questionImportService): StreamedResponse
    {
        $this->authorize('viewAny', Import::class);

        $template = (string) $request->query('template', 'general');
        $format = strtolower((string) $request->query('format', 'csv'));

        if ($format === 'json') {
            $payload = $questionImportService->sampleJson($template);
            $filename = match ($template) {
                'mcq' => 'mcq-question-sample.json',
                'theory' => 'theory-question-sample.json',
                'structured_response' => 'structured-response-question-sample.json',
                default => 'question-import-sample.json',
            };

            return response()->streamDownload(function () use ($payload): void {
                echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
            }, $filename, [
                'Content-Type' => 'application/json',
            ]);
        }

        $rows = $questionImportService->sampleRows($template);
        $filename = $template === 'structured_response' ? 'structured-response-sample.csv' : 'question-import-sample.csv';

        return response()->streamDownload(function () use ($rows): void {
            $stream = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($stream, $row);
            }
            fclose($stream);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
