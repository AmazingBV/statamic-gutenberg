<?php

namespace Amazingbv\StatamicGutenberg\Tags;

use Amazingbv\StatamicGutenberg\GutenbergManager;
use Statamic\Tags\Tags;

class Gutenberg extends Tags
{
    public function index(): string
    {
        return $this->assets();
    }

    public function assets(): string
    {
        return (string) app(GutenbergManager::class)->frontendAssets();
    }

    public function styles(): string
    {
        return (string) app(GutenbergManager::class)->frontendStyles();
    }

    public function scripts(): string
    {
        return (string) app(GutenbergManager::class)->frontendScripts();
    }
}
