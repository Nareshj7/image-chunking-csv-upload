<?php

namespace Database\Factories;

use App\Models\ImportJob;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ImportJob>
 */
class ImportJobFactory extends Factory
{
    protected $model = ImportJob::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'type' => ImportJob::TYPE_CSV_IMPORT,
            'filename' => $this->faker->lexify('import_????.csv'),
            'total_rows' => 100,
            'processed_rows' => 100,
            'imported_count' => 80,
            'updated_count' => 10,
            'invalid_count' => 5,
            'duplicate_count' => 5,
            'status' => 'completed',
        ];
    }
}
