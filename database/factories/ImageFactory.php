<?php

namespace Database\Factories;

use App\Models\Image;
use App\Models\Upload;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Image>
 */
class ImageFactory extends Factory
{
    protected $model = Image::class;

    public function definition(): array
    {
        return [
            'upload_id' => Upload::factory(),
            'variant' => Image::VARIANT_ORIGINAL,
            'path' => 'images/' . $this->faker->uuid . '.jpg',
            'width' => 1024,
            'height' => 768,
            'size' => 512000,
            'checksum' => $this->faker->sha1(),
        ];
    }
}
