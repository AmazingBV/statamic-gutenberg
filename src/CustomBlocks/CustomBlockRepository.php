<?php

namespace Amazingbv\StatamicGutenberg\CustomBlocks;

class CustomBlockRepository
{
    private ?array $blocks = null;

    public function path(): string
    {
        return (string) config('statamic-gutenberg.custom_blocks_path', resource_path('vendor/statamic-gutenberg/blocks'));
    }

    public function all(): array
    {
        return array_values($this->blocksByName());
    }

    public function names(): array
    {
        return array_keys($this->blocksByName());
    }

    public function dependencyNames(): array
    {
        return array_values(array_unique(collect($this->all())
            ->flatMap(fn (array $block) => $block['dependencies'] ?? [])
            ->filter(fn ($name) => is_string($name) && $this->validBlockName($name))
            ->all()));
    }

    public function find(string $name): ?array
    {
        return $this->blocksByName()[$name] ?? null;
    }

    public function editorPayload(): array
    {
        return collect($this->all())
            ->map(fn (array $block) => [
                'name' => $block['name'],
                'metadata' => $block['metadata'],
                'editorScripts' => $block['editorScripts'],
                'editorStyles' => $block['editorStyles'],
            ])
            ->values()
            ->all();
    }

    public function frontendStyleUrls(): array
    {
        return $this->uniqueUrls(collect($this->all())
            ->flatMap(fn (array $block) => $block['frontendStyles'])
            ->all());
    }

    public function frontendScriptAssets(): array
    {
        return $this->uniqueAssets(collect($this->all())
            ->flatMap(fn (array $block) => $block['frontendScripts'])
            ->all());
    }

    public function publicAssetFile(string $path): ?string
    {
        $base = realpath($this->path());

        if (! $base) {
            return null;
        }

        $relative = $this->publicAssetRelativePath($path);

        if (! $relative) {
            return null;
        }

        $file = realpath($base.'/'.$relative);

        if (! $file || ! is_file($file) || ! str_starts_with($file, $base.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return in_array($file, $this->publicAssetFiles($base), true) ? $file : null;
    }

    private function blocksByName(): array
    {
        if ($this->blocks !== null) {
            return $this->blocks;
        }

        $base = realpath($this->path());

        if (! $base || ! is_dir($base)) {
            return $this->blocks = [];
        }

        $files = glob($base.'/*/block.json') ?: [];
        sort($files);

        $blocks = [];

        foreach ($files as $file) {
            $block = $this->blockFromJson($base, $file);

            if (! $block) {
                continue;
            }

            $blocks[$block['name']] = $block;
        }

        return $this->blocks = $blocks;
    }

    private function blockFromJson(string $base, string $file): ?array
    {
        $directory = realpath(dirname($file));

        if (! $directory || ! str_starts_with($directory, $base.DIRECTORY_SEPARATOR)) {
            return null;
        }

        $metadata = json_decode((string) file_get_contents($file), true);

        if (! is_array($metadata)) {
            return null;
        }

        $name = (string) ($metadata['name'] ?? '');

        if (! $this->validBlockName($name)) {
            return null;
        }

        return [
            'name' => $name,
            'slug' => basename($directory),
            'path' => $directory,
            'metadata' => $this->editorMetadata($metadata),
            'dependencies' => $this->dependencyNamesFromMetadata($metadata),
            'render' => $this->renderFile($directory, $metadata),
            'editorScripts' => $this->editorScripts($base, $directory, $metadata),
            'editorStyles' => $this->editorStyles($base, $directory, $metadata),
            'frontendScripts' => $this->frontendScripts($base, $directory, $metadata),
            'frontendStyles' => $this->frontendStyles($base, $directory, $metadata),
        ];
    }

    private function validBlockName(string $name): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9-]*\/[a-z0-9][a-z0-9-]*$/', $name);
    }

    private function editorMetadata(array $metadata): array
    {
        $remove = [
            '$schema',
            'editorScript',
            'editorScriptModule',
            'script',
            'viewScript',
            'viewScriptModule',
            'editorStyle',
            'style',
            'viewStyle',
            'render',
        ];

        foreach ($remove as $key) {
            unset($metadata[$key]);
        }

        $metadata['apiVersion'] = (int) ($metadata['apiVersion'] ?? 3);

        return $metadata;
    }

    private function dependencyNamesFromMetadata(array $metadata): array
    {
        $statamic = is_array($metadata['statamic'] ?? null) ? $metadata['statamic'] : [];

        return array_values(array_unique(array_filter([
            ...$this->blockNamesFromList($statamic['requiredBlocks'] ?? null),
            ...$this->blockNamesFromList($statamic['required_blocks'] ?? null),
            ...$this->blockNamesFromList($metadata['allowedBlocks'] ?? null),
            ...$this->blockNamesFromTemplate($metadata['template'] ?? null),
        ], fn ($name) => is_string($name) && $this->validBlockName($name))));
    }

    private function blockNamesFromList(mixed $value): array
    {
        if (is_string($value)) {
            return [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->flatMap(fn ($item) => $this->blockNamesFromList($item))
            ->values()
            ->all();
    }

    private function blockNamesFromTemplate(mixed $template): array
    {
        if (! is_array($template)) {
            return [];
        }

        return collect($template)
            ->flatMap(function ($item) {
                if (is_string($item)) {
                    return [$item];
                }

                if (! is_array($item)) {
                    return [];
                }

                $names = [];

                if (isset($item[0]) && is_string($item[0])) {
                    $names[] = $item[0];
                }

                if (isset($item[2])) {
                    $names = [
                        ...$names,
                        ...$this->blockNamesFromTemplate($item[2]),
                    ];
                }

                return $names;
            })
            ->values()
            ->all();
    }

    private function renderFile(string $directory, array $metadata): ?string
    {
        foreach ($this->assetValues($metadata['render'] ?? null) as $value) {
            $file = $this->localFile($directory, $value);

            if ($file && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                return $file;
            }
        }

        $conventional = $directory.'/block.php';

        return is_file($conventional) ? $conventional : null;
    }

    private function editorScripts(string $base, string $directory, array $metadata): array
    {
        $scripts = [
            ...$this->assets($base, $directory, $metadata['script'] ?? null, false),
            ...$this->assets($base, $directory, $metadata['editorScript'] ?? null, false),
            ...$this->assets($base, $directory, $metadata['editorScriptModule'] ?? null, true),
        ];

        return $this->uniqueAssets([
            ...$scripts,
            ...($scripts === [] ? $this->firstConventionalAsset($base, $directory, ['block.js', 'block-src.js'], false) : []),
        ]);
    }

    private function editorStyles(string $base, string $directory, array $metadata): array
    {
        $styles = [
            ...$this->styleUrls($base, $directory, $metadata['style'] ?? null),
            ...$this->styleUrls($base, $directory, $metadata['editorStyle'] ?? null),
        ];

        return $this->uniqueUrls([
            ...$styles,
            ...($styles === [] ? $this->firstConventionalStyleUrl($base, $directory, ['block.css']) : []),
        ]);
    }

    private function frontendScripts(string $base, string $directory, array $metadata): array
    {
        $scripts = [
            ...$this->assets($base, $directory, $metadata['script'] ?? null, false),
            ...$this->assets($base, $directory, $metadata['viewScript'] ?? null, false),
            ...$this->assets($base, $directory, $metadata['viewScriptModule'] ?? null, true),
        ];

        return $this->uniqueAssets([
            ...$scripts,
            ...($scripts === [] ? $this->firstConventionalAsset($base, $directory, ['view.js'], false) : []),
        ]);
    }

    private function frontendStyles(string $base, string $directory, array $metadata): array
    {
        $styles = [
            ...$this->styleUrls($base, $directory, $metadata['style'] ?? null),
            ...$this->styleUrls($base, $directory, $metadata['viewStyle'] ?? null),
        ];

        return $this->uniqueUrls([
            ...$styles,
            ...($styles === [] ? $this->firstConventionalStyleUrl($base, $directory, ['block.css']) : []),
        ]);
    }

    private function styleUrls(string $base, string $directory, mixed $value): array
    {
        return collect($this->assets($base, $directory, $value, false))
            ->pluck('src')
            ->values()
            ->all();
    }

    private function conventionalStyleUrls(string $base, string $directory, array $files): array
    {
        return collect($this->conventionalAssets($base, $directory, $files, false))
            ->pluck('src')
            ->values()
            ->all();
    }

    private function firstConventionalAsset(string $base, string $directory, array $files, bool $module): array
    {
        foreach ($files as $file) {
            if (is_file($directory.'/'.$file)) {
                $asset = $this->asset($base, $directory, 'file:./'.$file, $module);

                return $asset ? [$asset] : [];
            }
        }

        return [];
    }

    private function firstConventionalStyleUrl(string $base, string $directory, array $files): array
    {
        return collect($this->firstConventionalAsset($base, $directory, $files, false))
            ->pluck('src')
            ->values()
            ->all();
    }

    private function assets(string $base, string $directory, mixed $value, bool $module): array
    {
        return collect($this->assetValues($value))
            ->map(fn (string $asset) => $this->asset($base, $directory, $asset, $module))
            ->filter()
            ->values()
            ->all();
    }

    private function conventionalAssets(string $base, string $directory, array $files, bool $module): array
    {
        return collect($files)
            ->map(fn (string $file) => is_file($directory.'/'.$file) ? $this->asset($base, $directory, 'file:./'.$file, $module) : null)
            ->filter()
            ->values()
            ->all();
    }

    private function asset(string $base, string $directory, string $value, bool $module): ?array
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(https?:)?\/\//i', $value) || str_starts_with($value, '/')) {
            return ['src' => $value, 'module' => $module];
        }

        $file = $this->localFile($directory, $value);

        if (! $file) {
            return null;
        }

        return [
            'src' => $this->publicUrl($base, $file),
            'module' => $module,
        ];
    }

    private function localFile(string $directory, string $value): ?string
    {
        $relative = trim($value);
        $relative = preg_replace('/^file:/', '', $relative) ?? '';
        $relative = preg_replace('/^\.\//', '', $relative) ?? '';
        $relative = ltrim(str_replace('\\', '/', $relative), '/');

        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        $file = realpath($directory.'/'.$relative);

        return $file && is_file($file) && str_starts_with($file, $directory.DIRECTORY_SEPARATOR)
            ? $file
            : null;
    }

    private function publicUrl(string $base, string $file): string
    {
        $relative = ltrim(str_replace('\\', '/', substr($file, strlen($base))), '/');
        $version = $this->assetVersion($file);
        $query = $version === null ? '' : '?ver='.rawurlencode($version);

        return '/vendor/statamic-gutenberg/blocks/'.str_replace('%2F', '/', rawurlencode($relative)).$query;
    }

    private function publicAssetRelativePath(string $path): ?string
    {
        $relative = ltrim(str_replace('\\', '/', rawurldecode($path)), '/');

        return $relative === '' || str_contains($relative, '..') ? null : $relative;
    }

    private function publicAssetFiles(string $base): array
    {
        $files = [];

        foreach ($this->all() as $block) {
            $files = [
                ...$files,
                ...$this->publicAssetFilesFromAssets($base, $block['editorScripts'] ?? []),
                ...$this->publicAssetFilesFromAssets($base, $block['frontendScripts'] ?? []),
                ...$this->publicAssetFilesFromUrls($base, $block['editorStyles'] ?? []),
                ...$this->publicAssetFilesFromUrls($base, $block['frontendStyles'] ?? []),
            ];
        }

        return array_values(array_unique($files));
    }

    private function publicAssetFilesFromAssets(string $base, array $assets): array
    {
        return collect($assets)
            ->pluck('src')
            ->flatMap(fn ($url) => $this->publicAssetFilesFromUrls($base, is_string($url) ? [$url] : []))
            ->values()
            ->all();
    }

    private function publicAssetFilesFromUrls(string $base, array $urls): array
    {
        return collect($urls)
            ->filter(fn ($url) => is_string($url))
            ->flatMap(function (string $url) use ($base) {
                $file = $this->fileFromPublicUrl($base, $url);

                if (! $file) {
                    return [];
                }

                return strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'css'
                    ? [$file, ...$this->cssReferencedFiles($base, $file)]
                    : [$file];
            })
            ->values()
            ->all();
    }

    private function fileFromPublicUrl(string $base, string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $prefix = '/vendor/statamic-gutenberg/blocks/';

        if (! is_string($path) || ! str_starts_with($path, $prefix)) {
            return null;
        }

        $relative = $this->publicAssetRelativePath(substr($path, strlen($prefix)));

        if (! $relative) {
            return null;
        }

        $file = realpath($base.'/'.$relative);

        return $file && is_file($file) && str_starts_with($file, $base.DIRECTORY_SEPARATOR)
            ? $file
            : null;
    }

    private function cssReferencedFiles(string $base, string $cssFile): array
    {
        $contents = file_get_contents($cssFile);

        if (! is_string($contents)) {
            return [];
        }

        preg_match_all('/url\(\s*(["\']?)([^"\')]+)\1\s*\)/i', $contents, $matches);

        return collect($matches[2] ?? [])
            ->map(fn (string $url) => trim($url))
            ->reject(fn (string $url) => $url === '' || preg_match('/^(?:[a-z]+:|\/\/|\/|#)/i', $url))
            ->map(function (string $url) use ($base, $cssFile) {
                $path = parse_url($url, PHP_URL_PATH);
                $relative = is_string($path)
                    ? ltrim(str_replace('\\', '/', rawurldecode($path)), '/')
                    : null;

                if (! $relative || str_contains($relative, "\0")) {
                    return null;
                }

                $file = realpath(dirname($cssFile).'/'.$relative);

                return $file && is_file($file) && str_starts_with($file, $base.DIRECTORY_SEPARATOR)
                    ? $file
                    : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function assetValues(mixed $value): array
    {
        if (is_string($value)) {
            return [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        if (! array_is_list($value)) {
            foreach (['src', 'path', 'file'] as $key) {
                if (is_string($value[$key] ?? null)) {
                    return [$value[$key]];
                }
            }

            return [];
        }

        return collect($value)
            ->flatMap(fn ($item) => $this->assetValues($item))
            ->values()
            ->all();
    }

    private function assetVersion(string $file): ?string
    {
        $assetFile = dirname($file).'/'.pathinfo($file, PATHINFO_FILENAME).'.asset.php';

        if (is_file($assetFile)) {
            $definition = include $assetFile;

            if (is_array($definition) && array_key_exists('version', $definition)) {
                return match ($definition['version']) {
                    null => null,
                    false => (string) filemtime($file),
                    default => is_scalar($definition['version']) ? (string) $definition['version'] : (string) filemtime($file),
                };
            }
        }

        return (string) filemtime($file);
    }

    private function uniqueAssets(array $assets): array
    {
        $seen = [];

        return collect($assets)
            ->filter(fn ($asset) => is_array($asset) && is_string($asset['src'] ?? null))
            ->filter(function (array $asset) use (&$seen) {
                $key = ($asset['module'] ?? false ? 'module:' : 'script:').$asset['src'];

                if (isset($seen[$key])) {
                    return false;
                }

                $seen[$key] = true;

                return true;
            })
            ->values()
            ->all();
    }

    private function uniqueUrls(array $urls): array
    {
        return array_values(array_unique(array_filter($urls, fn ($url) => is_string($url) && $url !== '')));
    }
}
