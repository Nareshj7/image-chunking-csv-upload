<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChunkedUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class UploadController extends Controller
{
    public function __construct(private readonly ChunkedUploadService $chunkedUploadService)
    {
    }

    public function initialize(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'original_filename' => ['required', 'string'],
            'mime_type' => ['nullable', 'string'],
            'total_size' => ['required', 'integer', 'min:1'],
            'chunk_size' => ['required', 'integer', 'min:1'],
            'total_chunks' => ['required', 'integer', 'min:1'],
            'checksum' => ['nullable', 'string'],
        ]);

        $upload = $this->chunkedUploadService->initialize($validated);

        return response()->json([
            'uuid' => $upload->uuid,
            'status' => $upload->status,
        ], 201);
    }

    public function uploadChunk(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'chunk_number' => ['required', 'integer', 'min:1'],
            'chunk' => ['nullable', 'file'],
        ]);

        $contents = '';
        if ($request->hasFile('chunk')) {
            $contents = file_get_contents($request->file('chunk')->getRealPath());
        } else {
            $contents = $request->getContent();
        }

        if ($contents === false || $contents === '') {
            throw new RuntimeException('Chunk payload is empty.');
        }

        $upload = $this->chunkedUploadService->storeChunk($uuid, (int) $validated['chunk_number'], $contents);

        return response()->json([
            'status' => $upload->status,
            'uploaded_size' => $upload->uploaded_size,
            'total_size' => $upload->total_size,
            'completed_chunks' => $upload->completed_chunks,
        ]);
    }

    public function resume(string $uuid): JsonResponse
    {
        return response()->json($this->chunkedUploadService->getResumeInfo($uuid));
    }

    public function complete(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'checksum' => ['nullable', 'string'],
        ]);

        $upload = $this->chunkedUploadService->complete($uuid, $validated['checksum'] ?? null);

        return response()->json([
            'uuid' => $upload->uuid,
            'status' => $upload->status,
            'checksum' => $upload->checksum,
        ]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $this->chunkedUploadService->cancel($uuid);

        return response()->noContent();
    }
}
