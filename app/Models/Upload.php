<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Upload extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'original_filename',
        'mime_type',
        'total_size',
        'uploaded_size',
        'chunk_size',
        'total_chunks',
        'completed_chunks',
        'status',
        'checksum',
        'metadata',
    ];

    protected $casts = [
        'completed_chunks' => 'array',
        'metadata' => 'array',
        'uploaded_size' => 'integer',
        'total_size' => 'integer',
        'chunk_size' => 'integer',
        'total_chunks' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function uploadable()
    {
        return $this->morphTo();
    }

    public function images()
    {
        return $this->hasMany(Image::class);
    }
}
