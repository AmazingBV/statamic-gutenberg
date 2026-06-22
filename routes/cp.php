<?php

use Amazingbv\StatamicGutenberg\Http\Controllers\CP\AssetsController;
use Amazingbv\StatamicGutenberg\Http\Controllers\CP\EditorController;
use Amazingbv\StatamicGutenberg\Http\Controllers\CP\IconsController;
use Illuminate\Support\Facades\Route;

Route::prefix('amazingbv/statamic-gutenberg')
    ->name('amazingbv.statamic-gutenberg.')
    ->group(function () {
        Route::get('editor', EditorController::class)->name('editor');
        Route::get('assets', [AssetsController::class, 'index'])->name('assets.index');
        Route::post('assets', [AssetsController::class, 'upload'])->name('assets.upload');
        Route::get('icons', [IconsController::class, 'index'])->name('icons.index');
    });
