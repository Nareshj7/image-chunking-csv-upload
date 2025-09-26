<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportJob extends Model
{
    use HasFactory;

    public const TYPE_CSV_IMPORT = 'csv_import';

    protected $fillable = [
        'uuid',
        'type',
        'filename',
        'total_rows',
        'processed_rows',
        'imported_count',
        'updated_count',
        'invalid_count',
        'duplicate_count',
        'status',
        'errors',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'total_rows' => 'integer',
        'processed_rows' => 'integer',
        'imported_count' => 'integer',
        'updated_count' => 'integer',
        'invalid_count' => 'integer',
        'duplicate_count' => 'integer',
        'errors' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
