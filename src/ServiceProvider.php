<?php

namespace Amazingbv\StatamicGutenberg;

use Amazingbv\StatamicGutenberg\Blocks\BlockParser;
use Amazingbv\StatamicGutenberg\Blocks\BlockRegistry;
use Amazingbv\StatamicGutenberg\Blocks\BlockRenderer;
use Amazingbv\StatamicGutenberg\Blocks\Sanitizer;
use Amazingbv\StatamicGutenberg\Http\Controllers\ThemeAssetController;
use Amazingbv\StatamicGutenberg\Icons\IconRepository;
use Amazingbv\StatamicGutenberg\Patterns\PatternRepository;
use Illuminate\Support\Facades\Route;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $vite = [
        'input' => [
            'resources/js/addon.js',
            'resources/css/addon.css',
            'resources/js/frontend.js',
            'resources/css/frontend.css',
        ],
        'publicDirectory' => 'resources/dist',
    ];

    public function register()
    {
        $this->app->singleton(BlockParser::class);
        $this->app->singleton(BlockRegistry::class);
        $this->app->singleton(Sanitizer::class);
        $this->app->singleton(BlockRenderer::class);
        $this->app->singleton(IconRepository::class);
        $this->app->singleton(PatternRepository::class);
        $this->app->singleton(ThemeJson::class);
        $this->app->singleton(GutenbergManager::class);
        $this->app->alias(GutenbergManager::class, 'statamic-gutenberg');
    }

    public function bootAddon()
    {
        $patternStubs = dirname(__DIR__).'/resources/stubs/patterns';

        $this->publishes([
            "{$patternStubs}/content/collections/gutenberg_patterns.yaml" => base_path('content/collections/gutenberg_patterns.yaml'),
            "{$patternStubs}/content/taxonomies/gutenberg_pattern_categories.yaml" => base_path('content/taxonomies/gutenberg_pattern_categories.yaml'),
            "{$patternStubs}/resources/blueprints/collections/gutenberg_patterns/pattern.yaml" => resource_path('blueprints/collections/gutenberg_patterns/pattern.yaml'),
            "{$patternStubs}/resources/blueprints/taxonomies/gutenberg_pattern_categories/category.yaml" => resource_path('blueprints/taxonomies/gutenberg_pattern_categories/category.yaml'),
        ], 'statamic-gutenberg-patterns');

        Route::get('/vendor/statamic-gutenberg/theme/{path}', ThemeAssetController::class)
            ->where('path', '.*')
            ->name('statamic-gutenberg.theme-assets');

        if (! class_exists('Gutenberg')) {
            class_alias(\Amazingbv\StatamicGutenberg\Facades\Gutenberg::class, 'Gutenberg');
        }
    }
}
