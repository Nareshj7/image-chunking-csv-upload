<?php

namespace App\Console\Commands;

use App\Models\ImportJob;
use App\Services\ProductCsvImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;

class ImportProductsFromCsv extends Command
{
    protected $signature = 'products:import {file : The CSV file path} {--job=}';

    protected $description = 'Import or upsert products from a CSV file.';

    public function __construct(private readonly ProductCsvImportService $importService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $file = (string) $this->argument('file');

        if (! file_exists($file)) {
            $this->error('File does not exist: ' . $file);
            return self::FAILURE;
        }

        $jobUuid = $this->option('job');
        $job = $jobUuid
            ? ImportJob::query()->where('uuid', $jobUuid)->first()
            : null;

        if (! $job) {
            $job = ImportJob::query()->create([
                'uuid' => (string) Str::uuid(),
                'type' => ImportJob::TYPE_CSV_IMPORT,
                'filename' => basename($file),
                'status' => 'processing',
                'started_at' => now(),
            ]);
        } else {
            $job->fill([
                'status' => 'processing',
                'started_at' => now(),
                'errors' => null,
            ])->save();
        }

        try {
            $summary = $this->importService->import($file, $job);
        } catch (RuntimeException $exception) {
            $job->fill([
                'status' => 'failed',
                'completed_at' => now(),
            ])->save();

            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $this->info('Import completed.');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total', $summary['total']],
                ['Imported', $summary['imported']],
                ['Updated', $summary['updated']],
                ['Invalid', $summary['invalid']],
                ['Duplicates', $summary['duplicates']],
            ]
        );

        if ($summary['errors']) {
            $this->warn('Errors:');
            foreach ($summary['errors'] as $line => $message) {
                $this->line(sprintf('Line %d: %s', $line, $message));
            }
        }

        return self::SUCCESS;
    }
}
