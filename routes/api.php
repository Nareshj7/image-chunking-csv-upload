<?php

use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\ProductImageController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Support\Facades\Route;

Route::prefix('import')->group(function () {
    Route::post('csv', [ImportController::class, 'upload']);
    Route::get('status/{uuid}', [ImportController::class, 'status']);
    Route::get('download/{uuid}', [ImportController::class, 'downloadErrors']);
});

Route::prefix('upload')->group(function () {
    Route::post('initialize', [UploadController::class, 'initialize']);
    Route::post('{uuid}/chunk', [UploadController::class, 'uploadChunk']);
    Route::get('{uuid}/resume', [UploadController::class, 'resume']);
    Route::post('{uuid}/complete', [UploadController::class, 'complete']);
    Route::delete('{uuid}', [UploadController::class, 'destroy']);
});

Route::post('products/{sku}/image', [ProductImageController::class, 'attach']);
