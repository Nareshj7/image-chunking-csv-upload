<?php

namespace Database\Factories;

use App\Models\Upload;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Upload>
 */
class UploadFactory extends Factory
{
    protected $model = Upload::class;

    public function definition(): array
    {
        $totalChunks = $this->faker->numberBetween(2, 10);
        $chunkSize = 1024 * 1024;
        $totalSize = $chunkSize * $totalChunks;

        return [
            'uuid' => (string) Str::uuid(),
            'original_filename' => $this->faker->lexify('file_????.jpg'),
            'mime_type' => 'image/jpeg',
            'total_size' => $totalSize,
            'uploaded_size' => $totalSize,
            'chunk_size' => $chunkSize,
            'total_chunks' => $totalChunks,
            'completed_chunks' => range(1, $totalChunks),
            'status' => 'completed',
        ];
    }
}
