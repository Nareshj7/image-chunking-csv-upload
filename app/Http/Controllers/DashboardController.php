<?php

namespace App\Http\Controllers;

use App\Models\ImportJob;
use App\Models\Upload;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $latestImport = ImportJob::query()->latest()->first();

        $summary = [
            'total' => $latestImport?->total_rows,
            'imported' => $latestImport?->imported_count,
            'updated' => $latestImport?->updated_count,
            'invalid' => $latestImport?->invalid_count,
            'duplicates' => $latestImport?->duplicate_count,
        ];

        return view('dashboard', [
            'summary' => $summary,
            'importErrors' => $latestImport?->errors ?? [],
            'latestJobUuid' => $latestImport?->uuid,
            'recentImports' => ImportJob::query()->latest()->limit(5)->get(),
            'recentUploads' => Upload::query()->latest()->limit(5)->get(),
        ]);
    }
}
