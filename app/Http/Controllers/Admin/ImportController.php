<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConfirmQuestionImportRequest;
use App\Http\Requests\Admin\StoreQuestionImportRequest;
use App\Jobs\ProcessQuestionImportJob;
use App\Models\Import;
use App\Services\Import\QuestionImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Import::class);

        $imports = Import::query()
            ->with('uploadedBy:id,name')
            ->latest('id')
            ->paginate(12);

        return view('pages.admin.imports.index', [
            'imports' => $imports,
        ]);
    }

    public function store(StoreQuestionImportRequest $request, QuestionImportService $questionImportService): RedirectResponse
    {
        $import = $questionImportService->createImportFromUpload(
            $request->file('csv_file'),
            $request->user(),
            $request->boolean('allow_create_subjects'),
            $request->boolean('allow_create_topics'),
        );

        return redirect()
            ->route('admin.imports.show', $import)
            ->with('success', 'CSV uploaded and validated. Review the preview before importing.');
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

        ProcessQuestionImportJob::dispatch($import->id)->afterCommit();

        return redirect()
            ->route('admin.imports.show', $import)
            ->with('success', 'Import started. Refresh this page to monitor counts and row outcomes.');
    }
}
