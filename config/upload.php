<?php

return [
    'chunk_size' => env('CHUNK_SIZE', 1024 * 1024),
    'max_size' => env('MAX_UPLOAD_SIZE', 50 * 1024 * 1024),
    'temp_path' => storage_path('app/temp/uploads'),
    'image_path' => storage_path('app/public/images'),
    'variants' => [
        'thumb_256' => 256,
        'medium_512' => 512,
        'large_1024' => 1024,
    ],
];
