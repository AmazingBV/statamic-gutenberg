<?php

namespace Amazingbv\StatamicGutenberg;

use Amazingbv\StatamicGutenberg\Blocks\BlockParser;
use Amazingbv\StatamicGutenberg\Blocks\BlockRegistry;
use Amazingbv\StatamicGutenberg\Blocks\BlockRenderer;
use Amazingbv\StatamicGutenberg\CustomBlocks\CustomBlockRepository;
use Amazingbv\StatamicGutenberg\Patterns\PatternRepository;
use Illuminate\Support\HtmlString;

class GutenbergManager
{
    public function __construct(
        private BlockRegistry $registry,
        private BlockParser $parser,
        private BlockRenderer $renderer,
        private ThemeJson $themeJson,
        private PatternRepository $patterns,
        private CustomBlockRepository $customBlocks,
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
        $styles = collect($this->assetUrls('resources/css/frontend.css', 'css'))
            ->map(fn (string $url) => sprintf('<link rel="stylesheet" href="%s">', e($url)))
            ->all();

        if ($themeCss = $this->themeJson->frontendCss()) {
            $styles[] = sprintf('<style data-statamic-gutenberg-theme-json>%s</style>', $themeCss);
        }

        $styles = [
            ...$styles,
            ...collect($this->customBlocks->frontendStyleUrls())
                ->map(fn (string $url) => sprintf('<link rel="stylesheet" href="%s">', e($url)))
                ->all(),
        ];

        return new HtmlString(collect($styles)->filter()->implode("\n"));
    }

    public function frontendScripts(): HtmlString
    {
        return new HtmlString(collect($this->frontendScriptAssets())
            ->map(fn (array $asset) => sprintf(
                '<script%s src="%s"></script>',
                ($asset['module'] ?? false) ? ' type="module"' : '',
                e($asset['src'])
            ))
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
        return array_values(array_unique([
            ...$this->assetUrls('resources/css/frontend.css', 'css'),
            ...$this->customBlocks->frontendStyleUrls(),
        ]));
    }

    public function frontendScriptUrls(): array
    {
        return collect($this->frontendScriptAssets())
            ->pluck('src')
            ->values()
            ->all();
    }

    public function editorCustomBlocks(): array
    {
        return $this->customBlocks->editorPayload();
    }

    private function frontendScriptAssets(): array
    {
        $assets = collect($this->assetUrls('resources/js/frontend.js', 'js'))
            ->map(fn (string $url) => ['src' => $url, 'module' => true])
            ->all();

        return collect([
            ...$assets,
            ...$this->customBlocks->frontendScriptAssets(),
        ])
            ->unique(fn (array $asset) => (($asset['module'] ?? false) ? 'module:' : 'script:').($asset['src'] ?? ''))
            ->values()
            ->all();
    }

    public function allowedBlocks(?array $fieldAllowed = null): array
    {
        return $this->registry->allowedBlocks($fieldAllowed);
    }

    public function editorTheme(): ?array
    {
        return $this->themeJson->editorPayload();
    }

    public function editorPatterns(?array $allowedBlocks = null): array
    {
        return $this->patterns->editorPayload($allowedBlocks);
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
