<?php

namespace Amazingbv\StatamicGutenberg\Icons;

use Amazingbv\StatamicGutenberg\Blocks\Sanitizer;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class IconRepository
{
    public function __construct(private readonly Sanitizer $sanitizer)
    {
    }

    public function all(): array
    {
        return collect($this->configuredIcons())
            ->map(fn ($icon, $name) => $this->normalizeIcon($icon, (string) $name))
            ->filter()
            ->values()
            ->all();
    }

    public function find(string $name): ?array
    {
        return collect($this->all())->firstWhere('name', $name);
    }

    private function configuredIcons(): array
    {
        $icons = config('statamic-gutenberg.icons', []);

        foreach ($this->iconPaths() as $path) {
            $fileIcons = require $path;

            if (is_array($fileIcons)) {
                $icons = array_merge($icons, $fileIcons);
            }
        }

        return is_array($icons) ? $icons : [];
    }

    private function iconPaths(): array
    {
        $paths = [];
        $configured = config('statamic-gutenberg.icons_path');

        if (is_string($configured) && $configured !== '') {
            $paths[] = $configured;
        }

        $paths[] = resource_path('vendor/statamic-gutenberg/icons.php');
        $paths[] = resource_path('statamic-gutenberg/icons.php');

        return collect($paths)
            ->filter(fn ($path) => is_string($path) && is_file($path))
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeIcon(mixed $icon, string $fallbackName): ?array
    {
        $name = is_array($icon) ? (string) Arr::get($icon, 'name', $fallbackName) : $fallbackName;
        $content = is_array($icon)
            ? (string) (Arr::get($icon, 'svg') ?? Arr::get($icon, 'content') ?? '')
            : (string) $icon;

        $content = $this->sanitizer->sanitize($content);

        if ($name === '' || $content === '' || ! str_contains($content, '<svg')) {
            return null;
        }

        return [
            'name' => Str::slug($name),
            'label' => is_array($icon) ? (string) Arr::get($icon, 'label', Str::headline($name)) : Str::headline($name),
            'content' => $content,
        ];
    }
}
