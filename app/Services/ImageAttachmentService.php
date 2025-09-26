<?php

namespace App\Services;

use App\Models\Image;
use App\Models\Product;
use App\Models\Upload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ImageAttachmentService
{
    public function __construct(private readonly ImageProcessingService $imageProcessingService)
    {
    }

    public function attachPrimaryUploadToProduct(Product $product, string $uploadUuid): void
    {
        DB::transaction(function () use ($product, $uploadUuid) {
            /** @var Product $product */
            $product = Product::query()->lockForUpdate()->findOrFail($product->getKey());

            $upload = Upload::query()->where('uuid', $uploadUuid)->lockForUpdate()->first();
            if (! $upload) {
                throw new RuntimeException('Upload not found for provided UUID.');
            }

            if ($upload->status !== 'completed') {
                throw new RuntimeException('Upload must be completed before attachment.');
            }

            if ($product->primaryImage && $product->primaryImage->upload_id === $upload->id) {
                // Idempotent: same upload already attached
                return;
            }

            $images = $upload->images()->get()->keyBy('variant');
            if (! $images->has(Image::VARIANT_ORIGINAL)) {
                $metadata = $upload->metadata ?? [];
                $originalPath = $metadata['original_path'] ?? null;
                if (! $originalPath) {
                    throw new RuntimeException('Upload does not have processed images.');
                }
                $absolutePath = Storage::disk('public')->path($originalPath);
                $images = collect($this->imageProcessingService->generateVariants($upload, $absolutePath))->keyBy('variant');
            }

            /** @var Image|null $original */
            $original = $images->get(Image::VARIANT_ORIGINAL);
            if (! $original) {
                throw new RuntimeException('Original image variant is missing.');
            }

            // Detach previous product images to avoid stale associations
            $product->images()
                ->where('upload_id', '!=', $upload->id)
                ->update([
                    'imageable_type' => null,
                    'imageable_id' => null,
                ]);

            foreach ($upload->images as $image) {
                $image->imageable()->associate($product);
                $image->save();
            }

            $product->primary_image_id = $original->id;
            $product->save();
        });
    }
}
