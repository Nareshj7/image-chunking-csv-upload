<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImportJob;
use App\Services\ProductCsvImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportController extends Controller
{
    public function __construct(private readonly ProductCsvImportService $importService)
    {
    }

    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $storedPath = $validated['file']->store('imports');
        $absolutePath = Storage::path($storedPath);

        $job = ImportJob::query()->create([
            'uuid' => (string) Str::uuid(),
            'type' => ImportJob::TYPE_CSV_IMPORT,
            'filename' => basename($storedPath),
            'status' => 'processing',
            'started_at' => now(),
        ]);

        $summary = $this->importService->import($absolutePath, $job);

        return response()->json([
            'job_uuid' => $job->uuid,
            'summary' => $summary,
            'job' => $job->fresh(),
        ]);
    }

    public function status(string $uuid): JsonResponse
    {
        $job = ImportJob::query()->where('uuid', $uuid)->firstOrFail();

        return response()->json([
            'uuid' => $job->uuid,
            'status' => $job->status,
            'counts' => [
                'total' => $job->total_rows,
                'processed' => $job->processed_rows,
                'imported' => $job->imported_count,
                'updated' => $job->updated_count,
                'invalid' => $job->invalid_count,
                'duplicates' => $job->duplicate_count,
            ],
            'errors' => $job->errors,
        ]);
    }

    public function downloadErrors(string $uuid): Response
    {
        $job = ImportJob::query()->where('uuid', $uuid)->firstOrFail();
        $errors = $job->errors ?? [];

        if ($errors === []) {
            return response('No errors recorded.', 200, [
                'Content-Type' => 'text/plain',
            ]);
        }

        $callback = function () use ($errors): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['line', 'error']);
            foreach ($errors as $line => $message) {
                fputcsv($handle, [$line, $message]);
            }
            fclose($handle);
        };

        return response()->streamDownload($callback, 'import-errors-' . $uuid . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
