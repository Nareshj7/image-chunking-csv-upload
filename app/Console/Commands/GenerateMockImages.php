<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;

class GenerateMockImages extends Command
{
    protected $signature = 'products:generate-mock-images {directory=storage/app/mock-images} {--count=500} {--corrupt=10}';

    protected $description = 'Generate a directory filled with mock images for testing uploads.';

    public function handle(): int
    {
        $directory = (string) $this->argument('directory');
        $count = max(1, (int) $this->option('count'));
        $corrupt = max(0, min($count, (int) $this->option('corrupt')));

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException('Failed to create directory: ' . $directory);
        }

        for ($i = 0; $i < $count; $i++) {
            $filename = sprintf('%s/mock_%s.png', rtrim($directory, '/'), Str::random(12));

            if ($corrupt > 0 && random_int(0, $count) < $corrupt) {
                file_put_contents($filename, Str::random(50));
                $corrupt--;
                continue;
            }

            $width = random_int(256, 2048);
            $height = random_int(256, 2048);
            $image = imagecreatetruecolor($width, $height);
            $color = imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255));
            imagefilledrectangle($image, 0, 0, $width, $height, $color);

            $diagonalColor = imagecolorallocate($image, 255 - ($color & 0xFF), 255 - (($color >> 8) & 0xFF), 255 - (($color >> 16) & 0xFF));
            imageline($image, 0, 0, $width, $height, $diagonalColor);
            imageline($image, 0, $height, $width, 0, $diagonalColor);

            imagepng($image, $filename, 6);
            imagedestroy($image);
        }

        $this->info(sprintf('Generated %d images in %s.', $count, $directory));
        return self::SUCCESS;
    }
}
