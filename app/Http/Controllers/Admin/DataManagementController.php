<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WipeCurriculumRequest;
use App\Services\Admin\CurriculumDataManagementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class DataManagementController extends Controller
{
    public function index(CurriculumDataManagementService $service): View
    {
        return view('pages.admin.data-management.index', [
            'stats' => $service->stats(),
            'phrases' => WipeCurriculumRequest::phrases(),
        ]);
    }

    public function wipe(WipeCurriculumRequest $request, CurriculumDataManagementService $service): RedirectResponse
    {
        $scope = (string) $request->validated('scope');
        $summary = $service->wipe($scope);

        $parts = collect($summary)
            ->filter(fn ($count) => (int) $count > 0)
            ->map(fn ($count, $key) => sprintf('%d %s', (int) $count, str_replace('_', ' ', $key)))
            ->implode(', ');

        $message = $parts !== ''
            ? "Wipe completed: {$parts}."
            : 'Wipe completed. No matching records were found.';

        return redirect()
            ->route('admin.data-management.index')
            ->with('success', $message);
    }
}
