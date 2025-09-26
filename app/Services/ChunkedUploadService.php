<?php

namespace App\Services;

use App\Models\Upload;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ChunkedUploadService
{
    public function __construct(
        private readonly ImageProcessingService $imageProcessingService,
        private readonly FilesystemManager $filesystem
    ) {
    }

    public function initialize(array $attributes): Upload
    {
        $uuid = $attributes['uuid'] ?? (string) Str::uuid();
        $totalSize = (int) ($attributes['total_size'] ?? 0);
        $chunkSize = (int) ($attributes['chunk_size'] ?? config('upload.chunk_size'));
        $totalChunks = (int) ($attributes['total_chunks'] ?? 0);

        if ($totalSize <= 0 || $chunkSize <= 0 || $totalChunks <= 0) {
            throw new RuntimeException('Invalid upload metadata provided.');
        }

        $upload = Upload::query()->create([
            'uuid' => $uuid,
            'original_filename' => (string) ($attributes['original_filename'] ?? 'upload.bin'),
            'mime_type' => $attributes['mime_type'] ?? null,
            'total_size' => $totalSize,
            'uploaded_size' => 0,
            'chunk_size' => $chunkSize,
            'total_chunks' => $totalChunks,
            'completed_chunks' => [],
            'status' => 'pending',
            'checksum' => $attributes['checksum'] ?? null,
            'metadata' => [
                'extension' => $attributes['extension'] ?? pathinfo((string) ($attributes['original_filename'] ?? ''), PATHINFO_EXTENSION),
            ],
        ]);

        $this->ensureTempDirectory($upload->uuid);

        return $upload;
    }

    public function cancel(string $uuid): void
    {
        DB::transaction(function () use ($uuid) {
            $upload = Upload::query()->where('uuid', $uuid)->lockForUpdate()->first();
            if (! $upload) {
                return;
            }

            $this->cleanupChunks($upload->uuid);
            $upload->images()->delete();
            $upload->delete();
        });
    }

    public function storeChunk(string $uuid, int $chunkNumber, string $contents): Upload
    {
        return DB::transaction(function () use ($uuid, $chunkNumber, $contents) {
            $upload = Upload::query()->where('uuid', $uuid)->lockForUpdate()->first();

            if (! $upload) {
                throw new RuntimeException('Upload session not found.');
            }

            if ($chunkNumber < 1 || $chunkNumber > $upload->total_chunks) {
                throw new RuntimeException('Chunk number out of bounds.');
            }

            if (in_array($upload->status, ['processing', 'completed', 'failed'], true)) {
                throw new RuntimeException('Cannot upload chunks for the current upload status.');
            }

            $chunkPath = $this->chunkPath($upload->uuid, $chunkNumber);
            $this->ensureTempDirectory($upload->uuid);

            // Overwrite chunk to guarantee idempotency for resends
            file_put_contents($chunkPath, $contents);

            $completed = collect($upload->completed_chunks ?? [])
                ->push($chunkNumber)
                ->unique()
                ->sort()
                ->values()
                ->all();

            $upload->completed_chunks = $completed;
            $upload->uploaded_size = $this->calculateUploadedSize($upload);
            $upload->status = count($completed) === $upload->total_chunks ? 'processing' : 'uploading';
            $upload->save();

            return $upload;
        });
    }

    /**
     * @return array{completed_chunks: array<int>, missing_chunks: array<int>, status: string, uploaded_size:int, total_size:int}
     */
    public function getResumeInfo(string $uuid): array
    {
        $upload = Upload::query()->where('uuid', $uuid)->first();

        if (! $upload) {
            throw new RuntimeException('Upload session not found.');
        }

        $completed = collect($upload->completed_chunks ?? [])->map(fn ($chunk) => (int) $chunk)->unique()->sort()->values()->all();
        $missing = collect(range(1, $upload->total_chunks))
            ->diff($completed)
            ->values()
            ->all();

        return [
            'completed_chunks' => $completed,
            'missing_chunks' => $missing,
            'status' => $upload->status,
            'uploaded_size' => (int) $upload->uploaded_size,
            'total_size' => (int) $upload->total_size,
        ];
    }

    public function complete(string $uuid, ?string $checksum = null): Upload
    {
        return DB::transaction(function () use ($uuid, $checksum) {
            $upload = Upload::query()->where('uuid', $uuid)->lockForUpdate()->first();

            if (! $upload) {
                throw new RuntimeException('Upload session not found.');
            }

            if ($upload->status === 'completed') {
                return $upload;
            }

            $completedChunks = collect($upload->completed_chunks ?? [])->unique()->sort()->values();
            if ($completedChunks->count() !== $upload->total_chunks) {
                throw new RuntimeException('Upload is missing chunks and cannot be completed.');
            }

            $assembledPath = $this->assembleChunks($upload, $completedChunks->all());
            $calculatedChecksum = hash_file('sha256', $assembledPath);
            $expectedChecksum = $checksum ?? $upload->checksum;

            if ($expectedChecksum !== null && ! hash_equals($expectedChecksum, $calculatedChecksum)) {
                $upload->status = 'failed';
                $upload->save();
                throw new RuntimeException('Checksum mismatch detected.');
            }

            $upload->checksum = $calculatedChecksum;
            $upload->status = 'processing';
            $upload->save();

            $images = $this->imageProcessingService->generateVariants($upload, $assembledPath);

            $upload->status = 'completed';
            $upload->completed_at = now();
            $upload->metadata = array_merge($upload->metadata ?? [], [
                'original_path' => $images['original']->path ?? null,
            ]);
            $upload->save();

            $this->cleanupChunks($upload->uuid);

            return $upload;
        });
    }

    private function ensureTempDirectory(string $uuid): void
    {
        $path = $this->tempDirectory($uuid);
        if (! is_dir($path) && ! mkdir($path, 0777, true) && ! is_dir($path)) {
            throw new RuntimeException('Unable to create upload temp directory.');
        }
    }

    private function tempDirectory(string $uuid): string
    {
        return rtrim(config('upload.temp_path'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $uuid;
    }

    private function chunkPath(string $uuid, int $chunkNumber): string
    {
        return $this->tempDirectory($uuid) . DIRECTORY_SEPARATOR . sprintf('chunk_%05d.part', $chunkNumber);
    }

    /**
     * @param array<int, int> $chunks
     */
    private function assembleChunks(Upload $upload, array $chunks): string
    {
        $tempDir = $this->tempDirectory($upload->uuid);
        $assembledPath = $tempDir . DIRECTORY_SEPARATOR . 'assembled';

        $destination = fopen($assembledPath, 'wb');
        if ($destination === false) {
            throw new RuntimeException('Unable to assemble uploaded chunks.');
        }

        try {
            foreach ($chunks as $chunkNumber) {
                $chunkPath = $this->chunkPath($upload->uuid, $chunkNumber);
                $source = fopen($chunkPath, 'rb');
                if ($source === false) {
                    throw new RuntimeException('Missing chunk file during assembly.');
                }

                stream_copy_to_stream($source, $destination);
                fclose($source);
            }
        } finally {
            fclose($destination);
        }

        return $assembledPath;
    }

    private function cleanupChunks(string $uuid): void
    {
        $tempDir = $this->tempDirectory($uuid);
        if (is_dir($tempDir)) {
            $this->filesystem->deleteDirectory($tempDir);
        }
    }

    private function calculateUploadedSize(Upload $upload): int
    {
        $total = 0;
        foreach ($upload->completed_chunks ?? [] as $chunk) {
            $path = $this->chunkPath($upload->uuid, (int) $chunk);
            if (is_file($path)) {
                $total += filesize($path) ?: 0;
            }
        }

        return $total;
    }
}
