<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    use HasFactory;

    public const VARIANT_ORIGINAL = 'original';
    public const VARIANT_256 = 'thumb_256';
    public const VARIANT_512 = 'medium_512';
    public const VARIANT_1024 = 'large_1024';

    protected $fillable = [
        'upload_id',
        'variant',
        'path',
        'width',
        'height',
        'size',
        'checksum',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'size' => 'integer',
    ];

    public function upload()
    {
        return $this->belongsTo(Upload::class);
    }

    public function imageable()
    {
        return $this->morphTo();
    }
}
