<?php

namespace Amazingbv\StatamicGutenberg;

use Amazingbv\StatamicGutenberg\Blocks\BlockParser;
use Amazingbv\StatamicGutenberg\Blocks\BlockRegistry;
use Amazingbv\StatamicGutenberg\Blocks\BlockRenderer;
use Amazingbv\StatamicGutenberg\Blocks\Sanitizer;
use Amazingbv\StatamicGutenberg\Icons\IconRepository;
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
        $this->app->singleton(GutenbergManager::class);
        $this->app->alias(GutenbergManager::class, 'statamic-gutenberg');
    }

    public function bootAddon()
    {
        if (! class_exists('Gutenberg')) {
            class_alias(\Amazingbv\StatamicGutenberg\Facades\Gutenberg::class, 'Gutenberg');
        }
    }
}
