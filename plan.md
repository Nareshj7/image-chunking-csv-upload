# Bulk Import + Chunked Drag-and-Drop Image Upload - Implementation Plan

## Project Overview
**Selected Domain**: Products (unique by SKU)
**Database**: SQLite
**Framework**: Laravel (latest)
**Key Features**: CSV bulk import with upsert, chunked/resumable image uploads, multiple image variants

---

## Phase 1: Project Setup & Database Schema

### 1.1 Laravel Installation & Configuration
```bash
composer create-project laravel/laravel bulk-import-system
cd bulk-import-system
```

### 1.2 Database Schema Design

#### Products Table
```sql
- id (bigint, primary key)
- sku (string, unique, indexed)
- name (string)
- description (text, nullable)
- price (decimal 10,2)
- quantity (integer, default 0)
- status (enum: active, inactive, default: active)
- primary_image_id (bigint, nullable, foreign key)
- created_at (timestamp)
- updated_at (timestamp)
```

#### Uploads Table (for chunked upload tracking)
```sql
- id (bigint, primary key)
- uuid (string, unique, indexed)
- original_filename (string)
- mime_type (string)
- total_size (bigint)
- uploaded_size (bigint, default 0)
- chunk_size (integer)
- total_chunks (integer)
- completed_chunks (json - array of chunk numbers)
- status (enum: pending, uploading, processing, completed, failed)
- checksum (string, nullable)
- metadata (json, nullable)
- uploadable_type (string, nullable)
- uploadable_id (bigint, nullable)
- created_at (timestamp)
- updated_at (timestamp)
- completed_at (timestamp, nullable)
```

#### Images Table
```sql
- id (bigint, primary key)
- upload_id (bigint, foreign key)
- variant (enum: original, thumb_256, medium_512, large_1024)
- path (string)
- width (integer)
- height (integer)
- size (bigint)
- created_at (timestamp)
- updated_at (timestamp)
```

#### Import Jobs Table
```sql
- id (bigint, primary key)
- uuid (string, unique)
- type (enum: csv_import)
- filename (string)
- total_rows (integer, default 0)
- processed_rows (integer, default 0)
- imported_count (integer, default 0)
- updated_count (integer, default 0)
- invalid_count (integer, default 0)
- duplicate_count (integer, default 0)
- status (enum: pending, processing, completed, failed)
- errors (json, nullable)
- started_at (timestamp, nullable)
- completed_at (timestamp, nullable)
- created_at (timestamp)
- updated_at (timestamp)
```

### 1.3 Migrations Creation
```bash
php artisan make:migration create_products_table
php artisan make:migration create_uploads_table
php artisan make:migration create_images_table
php artisan make:migration create_import_jobs_table
```

---

## Phase 2: Core Models & Relationships

### 2.1 Product Model
```php
class Product extends Model
{
    protected $fillable = ['sku', 'name', 'description', 'price', 'quantity', 'status'];
    
    public function primaryImage()
    {
        return $this->belongsTo(Image::class, 'primary_image_id');
    }
    
    public function uploads()
    {
        return $this->morphMany(Upload::class, 'uploadable');
    }
}
```

### 2.2 Upload Model
```php
class Upload extends Model
{
    protected $fillable = [
        'uuid', 'original_filename', 'mime_type', 'total_size',
        'uploaded_size', 'chunk_size', 'total_chunks', 'completed_chunks',
        'status', 'checksum', 'metadata'
    ];
    
    protected $casts = [
        'completed_chunks' => 'array',
        'metadata' => 'array'
    ];
    
    public function images()
    {
        return $this->hasMany(Image::class);
    }
    
    public function uploadable()
    {
        return $this->morphTo();
    }
}
```

### 2.3 Image Model
```php
class Image extends Model
{
    protected $fillable = [
        'upload_id', 'variant', 'path', 'width', 'height', 'size'
    ];
    
    public function upload()
    {
        return $this->belongsTo(Upload::class);
    }
}
```

### 2.4 ImportJob Model
```php
class ImportJob extends Model
{
    protected $fillable = [
        'uuid', 'type', 'filename', 'total_rows', 'processed_rows',
        'imported_count', 'updated_count', 'invalid_count', 
        'duplicate_count', 'status', 'errors'
    ];
    
    protected $casts = [
        'errors' => 'array'
    ];
}
```

---

## Phase 3: CSV Import Implementation

### 3.1 CSV Import Service
```php
namespace App\Services;

class CsvImportService
{
    // Main import method with chunked processing
    public function import($file, $chunkSize = 1000)
    
    // Validate CSV structure
    private function validateCsvStructure($headers)
    
    // Process single row with upsert logic
    private function processRow($row, $headers)
    
    // Generate import summary
    public function generateSummary($importJob)
}
```

### 3.2 Product Import Validator
```php
namespace App\Validators;

class ProductImportValidator
{
    // Required columns
    const REQUIRED_COLUMNS = ['sku', 'name', 'price', 'quantity'];
    
    // Validate single row
    public function validateRow($row)
    
    // Check for missing columns
    public function checkMissingColumns($row)
    
    // Validate data types and formats
    public function validateDataTypes($row)
}
```

### 3.3 CSV Import Job (Queue)
```php
namespace App\Jobs;

class ProcessCsvImport implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;
    
    public function handle()
    {
        // Process CSV in chunks
        // Update ImportJob progress
        // Handle errors gracefully
    }
}
```

### 3.4 CSV Import Controller
```php
namespace App\Http\Controllers;

class CsvImportController extends Controller
{
    public function upload(Request $request)
    {
        // Validate CSV file
        // Create ImportJob record
        // Dispatch ProcessCsvImport job
        // Return import UUID for tracking
    }
    
    public function status($uuid)
    {
        // Return import job status and summary
    }
}
```

---

## Phase 4: Chunked Image Upload Implementation

### 4.1 Chunked Upload Service
```php
namespace App\Services;

class ChunkedUploadService
{
    // Initialize upload session
    public function initializeUpload($filename, $totalSize, $chunkSize)
    
    // Process individual chunk
    public function processChunk($uploadUuid, $chunkNumber, $chunkData)
    
    // Verify chunk checksum
    private function verifyChunkChecksum($chunkData, $expectedChecksum)
    
    // Merge chunks after all uploaded
    public function mergeChunks($uploadUuid)
    
    // Resume interrupted upload
    public function getResumableInfo($uploadUuid)
    
    // Cleanup temporary chunks
    private function cleanupChunks($uploadUuid)
}
```

### 4.2 Image Processing Service
```php
namespace App\Services;

use Intervention\Image\ImageManager;

class ImageProcessingService
{
    const VARIANTS = [
        'thumb_256' => 256,
        'medium_512' => 512,
        'large_1024' => 1024
    ];
    
    // Generate all variants
    public function generateVariants($uploadId, $originalPath)
    
    // Create single variant maintaining aspect ratio
    private function createVariant($originalPath, $maxSize)
    
    // Calculate dimensions maintaining aspect ratio
    private function calculateDimensions($originalWidth, $originalHeight, $maxSize)
    
    // Store variant information
    private function storeVariant($uploadId, $variant, $path, $dimensions)
}
```

### 4.3 Upload Controller
```php
namespace App\Http\Controllers;

class UploadController extends Controller
{
    // Initialize upload session
    public function initialize(Request $request)
    {
        // Validate request
        // Create Upload record
        // Return upload UUID and chunk requirements
    }
    
    // Upload individual chunk
    public function uploadChunk(Request $request, $uuid)
    {
        // Validate chunk
        // Store chunk
        // Update Upload progress
        // Check if all chunks received
        // If complete, trigger processing
    }
    
    // Get resumable upload info
    public function resumeInfo($uuid)
    {
        // Return missing chunks and progress
    }
    
    // Link upload to product
    public function attachToProduct(Request $request, $productSku)
    {
        // Validate upload exists and is complete
        // Check if already attached (idempotent)
        // Set as primary image
        // Return success
    }
}
```

---

# Phase 5: Drag-and-Drop Frontend Implementation

## 5.1 Laravel Blade + Vanilla JS Structure

### `bulk-image-uploader.js`

```js
const state = {
    uploads: [], // Active uploads
    completed: [], // Completed uploads
    chunkSize: 1024 * 1024, // 1MB chunks
};

function handleDrop(files) {}
function initializeUpload(file) {}
function uploadChunks(file, uploadInfo) {}
function resumeUpload(uploadInfo) {}
function calculateChecksum(chunk) {}
function retryFailedChunk(uploadInfo, chunkNumber) {}
```

---

## Phase 6: Concurrency & Safety Measures

### 6.1 Database Locks
```php
// Use pessimistic locking for critical operations
DB::transaction(function () {
    $product = Product::where('sku', $sku)->lockForUpdate()->first();
    // Perform update
});
```

### 6.2 Queue Configuration
```php
// config/queue.php
'database' => [
    'driver' => 'database',
    'table' => 'jobs',
    'queue' => 'default',
    'retry_after' => 90,
    'after_commit' => false,
],
```

### 6.3 Rate Limiting
```php
// RouteServiceProvider.php
RateLimiter::for('uploads', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});
```

---

## Phase 7: Testing Strategy

### 7.1 Unit Tests

#### CSV Import Test
```php
class CsvImportTest extends TestCase
{
    public function test_upsert_creates_new_product()
    public function test_upsert_updates_existing_product()
    public function test_invalid_rows_are_counted()
    public function test_missing_columns_marked_invalid()
    public function test_duplicate_skus_handled_correctly()
}
```

#### Image Processing Test
```php
class ImageProcessingTest extends TestCase
{
    public function test_variants_maintain_aspect_ratio()
    public function test_all_variants_generated()
    public function test_variant_dimensions_correct()
}
```

#### Chunked Upload Test
```php
class ChunkedUploadTest extends TestCase
{
    public function test_chunk_resume_after_interruption()
    public function test_checksum_validation_blocks_invalid_chunks()
    public function test_duplicate_chunks_handled_idempotently()
    public function test_concurrent_chunk_uploads_safe()
}
```

### 7.2 Feature Tests
```php
class BulkImportFeatureTest extends TestCase
{
    public function test_complete_csv_import_flow()
    public function test_image_upload_and_attachment_flow()
    public function test_concurrent_imports_handled_safely()
}
```

---

## Phase 8: Mock Data Generation

### 8.1 CSV Generator Command
```php
php artisan make:command GenerateMockCsv

// Generate 10,000+ product rows
// Include valid, invalid, and duplicate entries
// Mix of products with and without images
```

### 8.2 Image Generator Script
```php
// Generate hundreds of test images
// Various sizes and formats
// Include corrupted files for testing
```

---

## Phase 9: API Endpoints

### 9.1 CSV Import Endpoints
```
POST   /api/import/csv                 - Upload CSV file
GET    /api/import/status/{uuid}       - Get import status
GET    /api/import/download/{uuid}     - Download error report
```

### 9.2 Image Upload Endpoints
```
POST   /api/upload/initialize          - Start upload session
POST   /api/upload/{uuid}/chunk        - Upload chunk
GET    /api/upload/{uuid}/resume       - Get resume info
POST   /api/upload/{uuid}/complete     - Finalize upload
DELETE /api/upload/{uuid}              - Cancel upload
```

### 9.3 Product Management Endpoints
```
POST   /api/products/{sku}/image       - Attach image to product
GET    /api/products/{sku}             - Get product with images
```

---

## Phase 10: Performance Optimizations

### 10.1 Database Optimizations
- Add composite indexes for frequent queries
- Use database transactions for bulk operations
- Implement query result caching

### 10.2 File Processing Optimizations
- Process images in background queues
- Use temporary storage for chunks
- Implement cleanup jobs for orphaned files

### 10.3 Memory Management
- Stream large CSV files instead of loading entirely
- Process in configurable chunk sizes
- Implement garbage collection for completed uploads

---


## Key Configuration Files

### .env additions
```
CHUNK_SIZE=1048576
MAX_UPLOAD_SIZE=52428800
IMAGE_VARIANTS=256,512,1024
CSV_CHUNK_SIZE=1000
QUEUE_CONNECTION=database
```

### config/upload.php
```php
return [
    'chunk_size' => env('CHUNK_SIZE', 1048576),
    'max_size' => env('MAX_UPLOAD_SIZE', 52428800),
    'temp_path' => storage_path('app/temp/uploads'),
    'image_path' => storage_path('app/public/images'),
];
```

---

## Success Metrics

✅ CSV import handles 10,000+ rows efficiently
✅ Upsert logic correctly creates/updates products
✅ Invalid rows tracked without stopping import
✅ Chunked uploads resume successfully
✅ Image variants respect aspect ratios
✅ Primary image attachment is idempotent
✅ Concurrent operations are thread-safe
✅ All unit tests passing
✅ Performance benchmarks met