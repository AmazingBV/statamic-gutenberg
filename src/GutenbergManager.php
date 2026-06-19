<?php

namespace Amazingbv\StatamicGutenberg;

use Amazingbv\StatamicGutenberg\Blocks\BlockParser;
use Amazingbv\StatamicGutenberg\Blocks\BlockRegistry;
use Amazingbv\StatamicGutenberg\Blocks\BlockRenderer;
use Illuminate\Support\HtmlString;

class GutenbergManager
{
    public function __construct(
        private BlockRegistry $registry,
        private BlockParser $parser,
        private BlockRenderer $renderer,
    ) {
        //
    }

    public function block(string $name, array|string|callable $definition): self
    {
        $this->registry->block($name, $definition);

        return $this;
    }

    public function parse(?string $content): array
    {
        return $this->parser->parse($content);
    }

    public function serialize(array $blocks): string
    {
        return $this->parser->serialize($blocks);
    }

    public function render(?string $content, array $options = []): HtmlString
    {
        return $this->renderer->render($content, $options);
    }

    public function frontendStyles(): HtmlString
    {
        return new HtmlString(collect($this->frontendStyleUrls())
            ->map(fn (string $url) => sprintf('<link rel="stylesheet" href="%s">', e($url)))
            ->implode("\n"));
    }

    public function frontendScripts(): HtmlString
    {
        return new HtmlString(collect($this->frontendScriptUrls())
            ->map(fn (string $url) => sprintf('<script type="module" src="%s"></script>', e($url)))
            ->implode("\n"));
    }

    public function frontendAssets(): HtmlString
    {
        return new HtmlString(collect([
            (string) $this->frontendStyles(),
            (string) $this->frontendScripts(),
        ])->filter()->implode("\n"));
    }

    public function frontendStyleUrls(): array
    {
        return $this->assetUrls('resources/css/frontend.css', 'css');
    }

    public function frontendScriptUrls(): array
    {
        return $this->assetUrls('resources/js/frontend.js', 'js');
    }

    public function allowedBlocks(?array $fieldAllowed = null): array
    {
        return $this->registry->allowedBlocks($fieldAllowed);
    }

    private function assetUrls(string $entry, string $type): array
    {
        $manifest = $this->assetManifest();
        $asset = $manifest[$entry] ?? null;

        if (! is_array($asset)) {
            return [];
        }

        $files = array_values(array_unique(array_filter([
            ...($asset['css'] ?? []),
            $asset['file'] ?? null,
        ], fn ($file) => is_string($file) && str_ends_with($file, ".{$type}"))));

        return array_map(
            fn (string $file) => "/vendor/statamic-gutenberg/build/{$file}",
            $files
        );
    }

    private function assetManifest(): array
    {
        foreach ($this->manifestPaths() as $path) {
            if (! is_file($path)) {
                continue;
            }

            $manifest = json_decode((string) file_get_contents($path), true);

            if (is_array($manifest)) {
                return $manifest;
            }
        }

        return [];
    }

    private function manifestPaths(): array
    {
        return [
            public_path('vendor/statamic-gutenberg/build/manifest.json'),
            dirname(__DIR__).'/resources/dist/build/manifest.json',
        ];
    }
}
