<?php

use Amazingbv\StatamicGutenberg\Http\Controllers\CP\AssetsController;
use Amazingbv\StatamicGutenberg\Http\Controllers\CP\BardPreviewController;
use Amazingbv\StatamicGutenberg\Http\Controllers\CP\BlockRendererController;
use Amazingbv\StatamicGutenberg\Http\Controllers\CP\EditorController;
use Amazingbv\StatamicGutenberg\Http\Controllers\CP\IconsController;
use Amazingbv\StatamicGutenberg\Http\Controllers\CP\PatternsController;
use Illuminate\Support\Facades\Route;

Route::prefix('amazingbv/statamic-gutenberg')
    ->name('amazingbv.statamic-gutenberg.')
    ->group(function () {
        Route::get('editor', EditorController::class)->name('editor');
        Route::get('assets', [AssetsController::class, 'index'])->name('assets.index');
        Route::post('assets', [AssetsController::class, 'upload'])->name('assets.upload');
        Route::get('assets/media', [AssetsController::class, 'show'])->name('assets.show');
        Route::patch('assets/media', [AssetsController::class, 'update'])->name('assets.update');
        Route::post('bard-preview', BardPreviewController::class)->name('bard-preview');
        Route::post('block-renderer', BlockRendererController::class)->name('block-renderer');
        Route::get('icons', [IconsController::class, 'index'])->name('icons.index');
        Route::get('patterns', [PatternsController::class, 'index'])->name('patterns.index');
    });
