<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'name',
        'description',
        'price',
        'quantity',
        'status',
        'primary_image_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
    ];

    public function primaryImage()
    {
        return $this->belongsTo(Image::class, 'primary_image_id');
    }

    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public function uploads()
    {
        return $this->morphMany(Upload::class, 'uploadable');
    }
}
