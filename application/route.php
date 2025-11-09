<?php
use think\Route;

Route::group('api', function () {
    Route::post('task/create', 'api/Midjourney/create');
    Route::post('task/upscale', 'api/Midjourney/upscale');
    Route::post('task/variation', 'api/Midjourney/variation');
    Route::post('task/describe', 'api/Midjourney/describe');
    Route::post('task/upload', 'api/Midjourney/uploadImage');
    Route::post('task/callback', 'api/Midjourney/callback');
    Route::get('task/list', 'api/Midjourney/list');
    Route::get('task/:id', 'api/Midjourney/detail');
});

Route::get('/', 'index/Index/index');
