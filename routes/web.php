<?php

use App\Http\Middleware\CheckIPAllow;
use Illuminate\Support\Facades\Route;



Route::middleware([\App\Http\Middleware\CheckIPAllow::class])->group(function () {
    Route::get('/s3/presigned-url',[\App\Http\Controllers\S3ToolController::class, 'getPresignUrl']);
    Route::post('/cloudfront/invalidate',[\App\Http\Controllers\S3ToolController::class, 'invalidateCloudFront']);
});
Route::middleware([CheckIPAllow::class])->group(function () {

    Route::get('/', function() {
        return view('s3-upload');
    });

});
