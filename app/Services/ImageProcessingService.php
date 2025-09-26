<?php

namespace App\Services;

use App\Models\Image;
use App\Models\Upload;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ImageProcessingService
{
    /**
     * @return array<string, Image>
     */
    public function generateVariants(Upload $upload, string $sourcePath): array
    {
        if (! is_file($sourcePath)) {
            throw new RuntimeException('Source image for variant generation not found.');
        }

        $info = getimagesize($sourcePath);
        if ($info === false) {
            throw new RuntimeException('Uploaded file is not a valid image.');
        }

        [$width, $height, $type] = $info;
        $extension = $this->extensionFromImagetype((int) $type);

        $disk = Storage::disk('public');
        $directory = trim('images/' . $upload->uuid, '/');
        $disk->makeDirectory($directory);

        $variants = [
            Image::VARIANT_ORIGINAL => null,
        ] + config('upload.variants', []);

        $existing = $upload->images()->get()->keyBy('variant');
        $results = [];

        $originalFilename = $directory . '/original.' . $extension;
        $disk->put($originalFilename, file_get_contents($sourcePath));
        $results[Image::VARIANT_ORIGINAL] = $this->persistImageRecord(
            $upload,
            $existing->get(Image::VARIANT_ORIGINAL),
            Image::VARIANT_ORIGINAL,
            $originalFilename,
            $width,
            $height
        );

        foreach ($variants as $variant => $size) {
            if ($variant === Image::VARIANT_ORIGINAL) {
                continue;
            }

            $targetSize = is_int($size) ? $size : null;
            if (! $targetSize) {
                continue;
            }

            $variantFilename = $directory . '/' . $variant . '.' . $extension;
            $targetDimensions = $this->calculateVariantDimensions($width, $height, $targetSize);
            $this->resizeAndStore($sourcePath, $variantFilename, $type, $targetDimensions[0], $targetDimensions[1]);

            $results[$variant] = $this->persistImageRecord(
                $upload,
                $existing->get($variant),
                $variant,
                $variantFilename,
                $targetDimensions[0],
                $targetDimensions[1]
            );
        }

        return $results;
    }

    private function extensionFromImagetype(int $type): string
    {
        return match ($type) {
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_GIF => 'gif',
            IMAGETYPE_WEBP => 'webp',
            IMAGETYPE_BMP => 'bmp',
            default => 'jpg',
        };
    }

    private function resizeAndStore(string $sourcePath, string $destinationRelativePath, int $type, int $targetWidth, int $targetHeight): void
    {
        $createFunction = match ($type) {
            IMAGETYPE_PNG => 'imagecreatefrompng',
            IMAGETYPE_GIF => 'imagecreatefromgif',
            IMAGETYPE_WEBP => 'imagecreatefromwebp',
            IMAGETYPE_BMP => 'imagecreatefrombmp',
            default => 'imagecreatefromjpeg',
        };

        if (! function_exists($createFunction)) {
            throw new RuntimeException('Required image processing extension is missing.');
        }

        $image = @$createFunction($sourcePath);
        if (! $image) {
            throw new RuntimeException('Failed to read source image.');
        }

        $originalWidth = imagesx($image);
        $originalHeight = imagesy($image);

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if (in_array($type, [IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefill($canvas, 0, 0, $transparent);
        }

        imagecopyresampled(
            $canvas,
            $image,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $originalWidth,
            $originalHeight
        );

        $saveFunction = match ($type) {
            IMAGETYPE_PNG => 'imagepng',
            IMAGETYPE_GIF => 'imagegif',
            IMAGETYPE_WEBP => 'imagewebp',
            IMAGETYPE_BMP => 'imagebmp',
            default => 'imagejpeg',
        };

        $tempFile = tmpfile();
        if ($tempFile === false) {
            throw new RuntimeException('Unable to create temporary file for variant.');
        }

        $tempMeta = stream_get_meta_data($tempFile);
        $tempPath = $tempMeta['uri'];

        $quality = $type === IMAGETYPE_JPEG ? 90 : null;
        if ($saveFunction === 'imagejpeg') {
            $saveFunction($canvas, $tempPath, $quality ?? 90);
        } elseif ($saveFunction === 'imagepng') {
            $saveFunction($canvas, $tempPath, 6);
        } else {
            $saveFunction($canvas, $tempPath);
        }

        imagedestroy($canvas);
        imagedestroy($image);

        $contents = file_get_contents($tempPath);
        fclose($tempFile);

        Storage::disk('public')->put($destinationRelativePath, $contents);
    }

    private function calculateVariantDimensions(int $width, int $height, int $target): array
    {
        $scale = min($target / $width, $target / $height, 1);
        $scaledWidth = max(1, (int) round($width * $scale));
        $scaledHeight = max(1, (int) round($height * $scale));

        return [$scaledWidth, $scaledHeight];
    }

    private function persistImageRecord(
        Upload $upload,
        ?Image $existing,
        string $variant,
        string $relativePath,
        int $width,
        int $height
    ): Image {
        $disk = Storage::disk('public');
        $fullPath = $disk->path($relativePath);
        $size = is_file($fullPath) ? filesize($fullPath) ?: 0 : 0;
        $checksum = is_file($fullPath) ? hash_file('sha256', $fullPath) : null;

        $attributes = [
            'path' => $relativePath,
            'width' => $width,
            'height' => $height,
            'size' => $size,
            'checksum' => $checksum,
        ];

        if ($existing) {
            $existing->fill($attributes);
            $existing->save();
            return $existing;
        }

        return $upload->images()->create(array_merge([
            'variant' => $variant,
        ], $attributes));
    }
}
