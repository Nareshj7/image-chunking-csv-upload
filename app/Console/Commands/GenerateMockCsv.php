<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;

class GenerateMockCsv extends Command
{
    protected $signature = 'products:generate-mock-csv {path=storage/app/mock_products.csv} {--rows=10000} {--duplicates=250} {--invalid=250}';

    protected $description = 'Generate a large mock CSV dataset for product imports.';

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        $rows = max(1, (int) $this->option('rows'));
        $duplicateBudget = max(0, (int) $this->option('duplicates'));
        $invalidBudget = max(0, (int) $this->option('invalid'));

        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException('Failed to create directory: ' . $directory);
        }

        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Unable to open file for writing: ' . $path);
        }

        $header = [
            'sku',
            'name',
            'description',
            'price',
            'quantity',
            'status',
            'primary_image_upload_uuid',
        ];
        fputcsv($handle, $header);

        $validRows = [];
        $statuses = ['active', 'inactive'];

        for ($i = 0; $i < $rows; $i++) {
            if ($duplicateBudget > 0 && $validRows !== [] && $this->shouldGenerateDuplicate($duplicateBudget, $rows - $i)) {
                $original = $validRows[array_rand($validRows)];
                fputcsv($handle, $original);
                $duplicateBudget--;
                continue;
            }

            $row = [
                'sku' => sprintf('SKU-%s', strtoupper(Str::random(8))),
                'name' => 'Product ' . Str::random(6),
                'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
                'price' => number_format(random_int(100, 99900) / 100, 2, '.', ''),
                'quantity' => (string) random_int(0, 1000),
                'status' => $statuses[array_rand($statuses)],
                'primary_image_upload_uuid' => random_int(0, 3) === 0 ? (string) Str::uuid() : '',
            ];

            if ($invalidBudget > 0 && $this->shouldGenerateInvalid($invalidBudget, $rows - $i)) {
                $row = $this->makeRowInvalid($row);
                $invalidBudget--;
            } else {
                $validRows[] = $row;
            }

            fputcsv($handle, $row);
        }

        fclose($handle);

        $this->info(sprintf(
            'Mock CSV generated at %s (%d rows, %d duplicates requested, %d invalid requested).',
            $path,
            $rows,
            $this->option('duplicates'),
            $this->option('invalid')
        ));

        return self::SUCCESS;
    }

    private function shouldGenerateDuplicate(int $remainingDuplicates, int $remainingRows): bool
    {
        return random_int(0, $remainingRows) < $remainingDuplicates;
    }

    private function shouldGenerateInvalid(int $remainingInvalid, int $remainingRows): bool
    {
        return random_int(0, $remainingRows) < $remainingInvalid;
    }

    /**
     * @param array<string, string> $row
     * @return array<string, string>
     */
    private function makeRowInvalid(array $row): array
    {
        $options = ['price', 'quantity', 'status'];
        $choice = $options[array_rand($options)];

        if ($choice === 'price') {
            $row['price'] = 'not-a-number';
        } elseif ($choice === 'quantity') {
            $row['quantity'] = '';
        } else {
            $row['status'] = 'unknown';
        }

        return $row;
    }
}
