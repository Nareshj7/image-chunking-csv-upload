<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\ImageAttachmentService;
use App\Services\ProductCsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class ProductCsvImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_creates_and_updates_products(): void
    {
        $this->app->instance(ImageAttachmentService::class, Mockery::mock(ImageAttachmentService::class));
        $this->app->get(ImageAttachmentService::class)
            ->shouldReceive('attachPrimaryUploadToProduct')
            ->withArgs(function (Product $product, string $uuid) {
                return $product instanceof Product && Str::isUuid($uuid);
            })
            ->zeroOrMoreTimes();

        Product::factory()->create([
            'sku' => 'SKU-001',
            'name' => 'Original Name',
            'price' => 5,
            'quantity' => 1,
            'status' => 'inactive',
        ]);

        $rows = [
            ['SKU-001', 'Updated Name', '12.50', '10', 'active', $this->uuid()],
            ['SKU-002', 'Product Two', '9.99', '4', 'inactive', $this->uuid()],
            ['SKU-002', 'Duplicate Product', '15.00', '2', 'active', $this->uuid()],
        ];

        $csvContent = "sku,name,price,quantity,status,primary_image_upload_uuid\n";
        foreach ($rows as $row) {
            $csvContent .= implode(',', $row) . "\n";
        }

        $path = storage_path('app/testing-products.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $csvContent);

        /** @var ProductCsvImportService $service */
        $service = $this->app->make(ProductCsvImportService::class);
        $summary = $service->import($path);

        $this->assertSame(3, $summary['total']);
        $this->assertSame(1, $summary['imported']);
        $this->assertSame(1, $summary['updated']);
        $this->assertSame(0, $summary['invalid']);
        $this->assertSame(1, $summary['duplicates']);

        $productOne = Product::query()->where('sku', 'SKU-001')->first();
        $this->assertNotNull($productOne);
        $this->assertSame('Updated Name', $productOne->name);
        $this->assertSame(10, $productOne->quantity);
        $this->assertSame('active', $productOne->status);

        $productTwo = Product::query()->where('sku', 'SKU-002')->first();
        $this->assertNotNull($productTwo);
        $this->assertSame('Product Two', $productTwo->name);
    }

    private function uuid(): string
    {
        return (string) Str::uuid();
    }
}
