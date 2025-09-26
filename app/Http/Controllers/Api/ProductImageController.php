<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ImageAttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductImageController extends Controller
{
    public function __construct(private readonly ImageAttachmentService $imageAttachmentService)
    {
    }

    public function attach(Request $request, string $sku): JsonResponse
    {
        $validated = $request->validate([
            'upload_uuid' => ['required', 'uuid'],
        ]);

        $product = Product::query()->where('sku', strtoupper($sku))->firstOrFail();
        $this->imageAttachmentService->attachPrimaryUploadToProduct($product, $validated['upload_uuid']);

        $product->load('primaryImage');

        return response()->json([
            'sku' => $product->sku,
            'primary_image' => $product->primaryImage ? [
                'variant' => $product->primaryImage->variant,
                'path' => $product->primaryImage->path,
            ] : null,
        ]);
    }
}
