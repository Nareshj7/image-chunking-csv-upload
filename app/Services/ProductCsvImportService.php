<?php

namespace App\Services;

use App\Models\ImportJob;
use App\Models\Product;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class ProductCsvImportService
{
    public function __construct(
        private readonly ImageAttachmentService $imageAttachmentService,
        private readonly DatabaseManager $db,
    ) {
    }

    /**
     * Import products from a CSV file path.
     *
     * @return array{total:int,imported:int,updated:int,invalid:int,duplicates:int,errors:array<int, string>}
     */
    public function import(string $path, ?ImportJob $job = null): array
    {
        if (! is_readable($path)) {
            throw new RuntimeException("CSV file is not readable: {$path}");
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Unable to open CSV file: {$path}");
        }

        $requiredColumns = config('import.required_columns', []);
        $optionalColumns = config('import.optional_columns', []);
        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);
            throw new RuntimeException('CSV file appears to be empty.');
        }

        $normalizedHeader = array_map(fn ($column) => Str::lower(trim((string) $column)), $header);

        $columnIndex = [];
        foreach ($normalizedHeader as $index => $column) {
            if ($column !== '') {
                $columnIndex[$column] = $index;
            }
        }

        $summary = [
            'total' => 0,
            'imported' => 0,
            'updated' => 0,
            'invalid' => 0,
            'duplicates' => 0,
            'errors' => [],
        ];

        $seenSkus = [];
        $rowNumber = 1; // account for header
        $chunkSize = (int) config('import.chunk_size', 1000);
        $batch = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            $summary['total']++;
            $batch[] = [$rowNumber, $row];

            if (count($batch) >= $chunkSize) {
                $this->processBatch($batch, $columnIndex, $requiredColumns, $optionalColumns, $seenSkus, $summary, $job);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $this->processBatch($batch, $columnIndex, $requiredColumns, $optionalColumns, $seenSkus, $summary, $job);
        }

        fclose($handle);

        if ($job) {
            $job->fill([
                'total_rows' => $summary['total'],
                'processed_rows' => $summary['total'],
                'imported_count' => $summary['imported'],
                'updated_count' => $summary['updated'],
                'invalid_count' => $summary['invalid'],
                'duplicate_count' => $summary['duplicates'],
                'status' => 'completed',
                'completed_at' => now(),
                'errors' => $summary['errors'],
            ])->save();
        }

        return $summary;
    }

    /**
     * @param array<int, array{0:int,1:array<int, string|null>}> $batch
     * @param array<string, int> $columnIndex
     * @param array<int, string> $requiredColumns
     * @param array<int, string> $optionalColumns
     * @param array<string, bool> $seenSkus
     * @param array<string, mixed> $summary
     */
    private function processBatch(
        array $batch,
        array $columnIndex,
        array $requiredColumns,
        array $optionalColumns,
        array &$seenSkus,
        array &$summary,
        ?ImportJob $job
    ): void {
        $this->db->transaction(function () use (
            $batch,
            $columnIndex,
            $requiredColumns,
            $optionalColumns,
            &$seenSkus,
            &$summary,
            $job
        ) {
            foreach ($batch as [$rowNumber, $row]) {
                $data = $this->mapRowToData($row, $columnIndex);

                $validationErrors = $this->validateRow($data, $requiredColumns, $optionalColumns);
                $sku = Arr::get($data, 'sku');

                if ($sku !== null) {
                    $normalizedSku = strtoupper($sku);
                    if (isset($seenSkus[$normalizedSku])) {
                        $summary['duplicates']++;
                        $summary['errors'][$rowNumber] = 'Duplicate SKU detected in CSV: ' . $normalizedSku;
                        continue;
                    }
                }

                if ($validationErrors !== null) {
                    $summary['invalid']++;
                    $summary['errors'][$rowNumber] = $validationErrors;
                    continue;
                }

                $normalizedSku = strtoupper((string) $data['sku']);
                $seenSkus[$normalizedSku] = true;

                $payload = [
                    'sku' => $normalizedSku,
                    'name' => trim((string) $data['name']),
                    'description' => Arr::get($data, 'description'),
                    'price' => (float) $data['price'],
                    'quantity' => (int) $data['quantity'],
                    'status' => $this->normalizeStatus((string) $data['status']),
                ];

                $imageUploadUuid = Arr::get($data, 'primary_image_upload_uuid');

                $existing = Product::query()->where('sku', $normalizedSku)->lockForUpdate()->first();

                if ($existing) {
                    $existing->fill($payload);
                    $dirty = $existing->isDirty();
                    $existing->save();
                    if ($dirty) {
                        $summary['updated']++;
                    }
                } else {
                    $existing = Product::query()->create($payload);
                    $summary['imported']++;
                }

                if ($imageUploadUuid) {
                    try {
                        $this->imageAttachmentService->attachPrimaryUploadToProduct($existing, (string) $imageUploadUuid);
                    } catch (RuntimeException $exception) {
                        Log::warning('Failed to attach primary image during import', [
                            'sku' => $normalizedSku,
                            'upload_uuid' => $imageUploadUuid,
                            'message' => $exception->getMessage(),
                        ]);
                        $summary['errors'][$rowNumber] = 'Image attachment failed: ' . $exception->getMessage();
                    }
                }

                if ($job) {
                    $job->increment('processed_rows');
                }
            }
        });
    }

    /**
     * @param array<int, string|null> $row
     * @param array<string, int> $columnIndex
     *
     * @return array<string, string|null>
     */
    private function mapRowToData(array $row, array $columnIndex): array
    {
        $data = [];
        foreach ($columnIndex as $column => $index) {
            $value = $row[$index] ?? null;
            $data[$column] = $value !== null ? trim((string) $value) : null;
        }

        return $data;
    }

    /**
     * Validate a mapped CSV row.
     */
    private function validateRow(array $data, array $requiredColumns, array $optionalColumns): ?string
    {
        foreach ($requiredColumns as $column) {
            if (! array_key_exists($column, $data) || ($data[$column] === null || $data[$column] === '')) {
                return sprintf('Missing required column "%s".', $column);
            }
        }

        if (! is_numeric($data['price'])) {
            return 'Invalid price value.';
        }

        if (! is_numeric($data['quantity'])) {
            return 'Invalid quantity value.';
        }

        $status = $this->normalizeStatus((string) $data['status']);
        if (! in_array($status, ['active', 'inactive'], true)) {
            return 'Invalid status value.';
        }

        if (isset($data['primary_image_upload_uuid']) && $data['primary_image_upload_uuid'] !== '') {
            if (! Str::isUuid($data['primary_image_upload_uuid'])) {
                return 'Invalid primary image upload UUID.';
            }
        }

        return null;
    }

    private function normalizeStatus(string $status): string
    {
        $normalized = strtolower(trim($status));
        return in_array($normalized, ['inactive', 'disabled'], true) ? 'inactive' : 'active';
    }
}
