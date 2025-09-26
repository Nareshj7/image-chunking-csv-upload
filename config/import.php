<?php

return [
    'chunk_size' => env('CSV_CHUNK_SIZE', 1000),
    'required_columns' => [
        'sku',
        'name',
        'price',
        'quantity',
        'status',
    ],
    'optional_columns' => [
        'description',
        'primary_image_upload_uuid',
    ],
];
