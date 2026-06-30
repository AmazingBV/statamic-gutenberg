<?php

namespace Amazingbv\StatamicGutenberg\Bard;

use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Fieldset;
use Statamic\Facades\YAML;
use Throwable;

class BardBlockRepository
{
    private ?array $blocks = null;

    public function all(): array
    {
        return array_values($this->blocksByName());
    }

    public function names(): array
    {
        return array_keys($this->blocksByName());
    }

    public function find(string $name): ?array
    {
        return $this->blocksByName()[$name] ?? null;
    }

    public function editorPayload(): array
    {
        if (! $this->enabled()) {
            return [];
        }

        return collect($this->all())
            ->map(fn (array $block) => [
                'name' => $block['name'],
                'metadata' => $block['metadata'],
                'set' => $block['set'],
                'source' => $block['source'],
                'sourceSlug' => $block['sourceSlug'],
                'fields' => $block['fields'],
                'defaults' => $block['defaults'],
            ])
            ->values()
            ->all();
    }

    public function render(string $name, array $attributes = []): string
    {
        $block = $this->find($name);

        if (! $block) {
            return $this->missingHtml($name, 'Unknown Bard block.');
        }

        $view = $this->resolveView($block);

        if (! $view) {
            return $this->missingHtml($name, 'No Bard render view configured.');
        }

        $values = $this->valuesFromAttributes($block, $attributes);

        try {
            return (string) View::make($view, [
                ...$values,
                'values' => $values,
                'bardSet' => $block['set'],
                'bardSource' => $block['source'],
                'bardBlock' => $block,
                'attributes' => $attributes,
            ])->render();
        } catch (Throwable $exception) {
            report($exception);

            return $this->missingHtml($name, 'Unable to render Bard block.');
        }
    }

    private function blocksByName(): array
    {
        if ($this->blocks !== null) {
            return $this->blocks;
        }

        if (! $this->enabled()) {
            return $this->blocks = [];
        }

        $sets = $this->discoverSets();
        $handleCounts = collect($sets)->countBy('setSlug');
        $used = [];
        $blocks = [];

        foreach ($sets as $set) {
            $slug = $set['setSlug'];
            $nameSlug = ($handleCounts[$slug] ?? 0) > 1
                ? $set['sourceSlug'].'-'.$slug
                : $slug;

            $nameSlug = $this->uniqueNameSlug($nameSlug, $used);
            $name = $this->blockNamespace().'/'.$nameSlug;
            $metadata = $this->metadataForSet($name, $set);

            $blocks[$name] = [
                ...$set,
                'name' => $name,
                'metadata' => $metadata,
            ];
        }

        ksort($blocks);

        return $this->blocks = $blocks;
    }

    private function discoverSets(): array
    {
        $sources = config('statamic-gutenberg.bard_blocks.sources', 'auto');
        $sets = [];

        if (is_array($sources)) {
            foreach ($sources as $source => $config) {
                $sets = [
                    ...$sets,
                    ...$this->setsFromBardField((string) $source, 'content', is_array($config) ? $config : []),
                ];
            }

            return $sets;
        }

        return [
            ...$this->setsFromBlueprints(),
            ...$this->setsFromFieldsets(),
        ];
    }

    private function setsFromBlueprints(): array
    {
        $directories = [
            (string) config('statamic-gutenberg.bard_blocks.blueprints_path', resource_path('blueprints')),
            ...collect(Blueprint::getAdditionalNamespaces())
                ->values()
                ->map(fn ($directory) => (string) $directory)
                ->all(),
        ];

        $sets = [];

        foreach ($this->uniqueDirectories($directories) as $directory) {
            foreach ($this->yamlFiles($directory) as $file) {
                $contents = $this->parseYamlFile($file);

                if (! is_array($contents)) {
                    continue;
                }

                $relative = $this->relativeYamlPath($directory, $file);

                foreach ($this->fieldItemsFromBlueprintContents($contents) as $item) {
                    $field = is_array($item['field'] ?? null) ? $item['field'] : [];

                    if (($field['type'] ?? null) !== 'bard') {
                        continue;
                    }

                    $handle = (string) ($item['handle'] ?? 'content');
                    $source = $this->sourceFromBlueprintPath($relative, $handle);
                    $sets = [
                        ...$sets,
                        ...$this->setsFromBardField($source, $handle, $field),
                    ];
                }
            }
        }

        return $sets;
    }

    private function setsFromFieldsets(): array
    {
        $sets = [];
        $directories = [
            (string) config('statamic-gutenberg.bard_blocks.fieldsets_path', resource_path('fieldsets')),
        ];

        foreach ($this->uniqueDirectories($directories) as $directory) {
            foreach ($this->yamlFiles($directory) as $file) {
                $contents = $this->parseYamlFile($file);

                if (! is_array($contents)) {
                    continue;
                }

                $fieldset = str_replace('/', '.', preg_replace('/\.ya?ml$/', '', $this->relativeYamlPath($directory, $file)) ?? '');

                foreach ($this->fieldItemsFromFieldsetContents($contents) as $item) {
                    $field = is_array($item['field'] ?? null) ? $item['field'] : [];

                    if (($field['type'] ?? null) !== 'bard') {
                        continue;
                    }

                    $handle = (string) ($item['handle'] ?? 'content');
                    $source = 'fieldset.'.$fieldset.'.'.$handle;
                    $sets = [
                        ...$sets,
                        ...$this->setsFromBardField($source, $handle, $field),
                    ];
                }
            }
        }

        return $sets;
    }

    private function setsFromBardField(string $source, string $fieldHandle, array $field): array
    {
        $sets = $field['sets'] ?? [];

        if (! is_array($sets)) {
            return [];
        }

        return collect($this->flattenSetGroups($sets))
            ->map(function (array $set) use ($source, $fieldHandle) {
                $handle = $this->safeHandle($set['handle'] ?? '');

                if ($handle === '') {
                    return null;
                }

                $fields = $this->normalizeFields($this->expandFieldItems($set['fields'] ?? []));
                $sourceSlug = $this->safeSlug($source);
                $setSlug = $this->safeSlug($handle);

                return [
                    'set' => $handle,
                    'setSlug' => $setSlug,
                    'source' => $source,
                    'sourceSlug' => $sourceSlug,
                    'fieldHandle' => $fieldHandle,
                    'title' => $this->stringValue($set['display'] ?? $set['title'] ?? null) ?: Str::headline($handle),
                    'description' => $this->stringValue($set['instructions'] ?? $set['description'] ?? null),
                    'icon' => $this->stringValue($set['icon'] ?? null) ?: 'block-default',
                    'fields' => $fields,
                    'defaults' => $this->defaultValues($fields),
                    'setConfig' => $this->safeConfig($set['config'] ?? []),
                    'view' => $this->stringValue($set['view'] ?? $set['template'] ?? $set['partial'] ?? null),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function flattenSetGroups(array $sets): array
    {
        $flattened = [];

        foreach ($sets as $handle => $set) {
            if (! is_array($set)) {
                continue;
            }

            if (isset($set['sets']) && is_array($set['sets'])) {
                $flattened = [
                    ...$flattened,
                    ...$this->flattenSetGroups($set['sets']),
                ];

                continue;
            }

            $flattened[] = [
                ...$set,
                'handle' => $set['handle'] ?? (is_string($handle) ? $handle : ''),
            ];
        }

        return $flattened;
    }

    private function normalizeFields(array $fields): array
    {
        return collect($fields)
            ->map(function (array $item) {
                $handle = $this->safeHandle($item['handle'] ?? '');
                $config = is_array($item['field'] ?? null) ? $item['field'] : [];

                if ($handle === '') {
                    return null;
                }

                return [
                    'handle' => $handle,
                    'type' => $this->safeHandle($config['type'] ?? 'text') ?: 'text',
                    'display' => $this->stringValue($config['display'] ?? null) ?: Str::headline($handle),
                    'instructions' => $this->stringValue($config['instructions'] ?? null),
                    'default' => $config['default'] ?? null,
                    'options' => $this->safeConfig($config['options'] ?? []),
                    'maxItems' => isset($config['max_items']) && is_numeric($config['max_items']) ? (int) $config['max_items'] : null,
                    'config' => $this->safeConfig($config),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function expandFieldItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->flatMap(function ($item) {
                if (! is_array($item)) {
                    return [];
                }

                if (isset($item['import']) && is_string($item['import'])) {
                    $fieldset = Fieldset::find($item['import']);

                    if (! $fieldset) {
                        return [];
                    }

                    $prefix = isset($item['prefix']) && is_string($item['prefix']) ? $item['prefix'] : '';

                    return collect($this->fieldItemsFromFieldsetContents($fieldset->contents()))
                        ->map(function (array $field) use ($prefix) {
                            if ($prefix !== '' && isset($field['handle'])) {
                                $field['handle'] = $prefix.$field['handle'];
                            }

                            return $field;
                        })
                        ->all();
                }

                return [$item];
            })
            ->values()
            ->all();
    }

    private function metadataForSet(string $name, array $set): array
    {
        return [
            'apiVersion' => 3,
            'name' => $name,
            'title' => $set['title'],
            'category' => 'statamic',
            'icon' => $set['icon'],
            'description' => $set['description'],
            'supports' => [
                'html' => false,
                'anchor' => true,
            ],
            'attributes' => [
                'bardSet' => [
                    'type' => 'string',
                    'default' => $set['set'],
                ],
                'bardSource' => [
                    'type' => 'string',
                    'default' => $set['source'],
                ],
                'values' => [
                    'type' => 'object',
                    'default' => (object) $set['defaults'],
                ],
            ],
            'statamic' => [
                'type' => 'bard',
                'set' => $set['set'],
                'source' => $set['source'],
                'fields' => $set['fields'],
            ],
        ];
    }

    private function valuesFromAttributes(array $block, array $attributes): array
    {
        $values = is_array($attributes['values'] ?? null) ? $attributes['values'] : [];

        foreach ($block['fields'] as $field) {
            $handle = $field['handle'];

            if (array_key_exists($handle, $attributes)) {
                $values[$handle] = $attributes[$handle];
            }
        }

        return [
            ...$block['defaults'],
            ...$values,
        ];
    }

    private function resolveView(array $block): ?string
    {
        $candidates = array_filter([
            $block['view'] ?? null,
            config("statamic-gutenberg.bard_blocks.views.{$block['name']}"),
            config("statamic-gutenberg.bard_blocks.views.{$block['source']}.{$block['set']}"),
            config("statamic-gutenberg.bard_blocks.views.{$block['set']}"),
        ], fn ($view) => is_string($view) && trim($view) !== '');

        foreach ($candidates as $candidate) {
            $view = $this->normalizeViewName((string) $candidate);

            if (View::exists($view)) {
                return $view;
            }
        }

        foreach ((array) config('statamic-gutenberg.bard_blocks.view_prefixes', []) as $prefix) {
            if (! is_string($prefix) || trim($prefix) === '') {
                continue;
            }

            foreach ([$block['setSlug'], $block['sourceSlug'].'-'.$block['setSlug']] as $suffix) {
                $view = trim($prefix, '.').'.'.$suffix;

                if (View::exists($view)) {
                    return $view;
                }
            }
        }

        return null;
    }

    private function missingHtml(string $name, string $message): string
    {
        if (config('statamic-gutenberg.bard_blocks.missing_behavior', 'empty') !== 'placeholder') {
            return '';
        }

        return '<div class="sgb-bard-block-missing" data-bard-block="'.e($name).'">'.e($message).'</div>';
    }

    private function fieldItemsFromBlueprintContents(array $contents): array
    {
        return collect($contents['tabs'] ?? [])
            ->flatMap(function ($tab) {
                if (isset($tab['sections']) && is_array($tab['sections'])) {
                    return collect($tab['sections'])->flatMap(fn ($section) => $section['fields'] ?? []);
                }

                return $tab['fields'] ?? [];
            })
            ->filter(fn ($field) => is_array($field))
            ->values()
            ->all();
    }

    private function fieldItemsFromFieldsetContents(array $contents): array
    {
        if (isset($contents['sections']) && is_array($contents['sections'])) {
            return collect($contents['sections'])
                ->flatMap(fn ($section) => is_array($section) ? ($section['fields'] ?? []) : [])
                ->filter(fn ($field) => is_array($field))
                ->values()
                ->all();
        }

        return collect($contents['fields'] ?? [])
            ->filter(fn ($field) => is_array($field))
            ->values()
            ->all();
    }

    private function sourceFromBlueprintPath(string $relative, string $fieldHandle): string
    {
        $path = preg_replace('/\.ya?ml$/', '', str_replace('\\', '/', $relative)) ?? $relative;
        $parts = array_values(array_filter(explode('/', $path), fn ($part) => $part !== ''));

        if (($parts[0] ?? null) === 'collections' && isset($parts[1])) {
            return $parts[1].'.'.$fieldHandle;
        }

        if (($parts[0] ?? null) === 'taxonomies' && isset($parts[1])) {
            return 'taxonomy.'.$parts[1].'.'.$fieldHandle;
        }

        return str_replace('/', '.', $path).'.'.$fieldHandle;
    }

    private function defaultValues(array $fields): array
    {
        return collect($fields)
            ->filter(fn (array $field) => array_key_exists('default', $field))
            ->mapWithKeys(fn (array $field) => [$field['handle'] => $field['default']])
            ->all();
    }

    private function uniqueNameSlug(string $slug, array &$used): string
    {
        $candidate = $slug;
        $index = 2;

        while (isset($used[$candidate])) {
            $candidate = $slug.'-'.$index;
            $index++;
        }

        $used[$candidate] = true;

        return $candidate;
    }

    private function yamlFiles(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            if (in_array($file->getExtension(), ['yaml', 'yml'], true)) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function parseYamlFile(string $file): mixed
    {
        try {
            return YAML::file($file)->parse();
        } catch (Throwable) {
            return null;
        }
    }

    private function relativeYamlPath(string $directory, string $file): string
    {
        return ltrim(str_replace('\\', '/', Str::after($file, rtrim($directory, '/').'/')), '/');
    }

    private function uniqueDirectories(array $directories): array
    {
        return collect($directories)
            ->filter(fn ($directory) => is_string($directory) && is_dir($directory))
            ->map(fn (string $directory) => realpath($directory) ?: $directory)
            ->unique()
            ->values()
            ->all();
    }

    private function safeConfig(mixed $value): mixed
    {
        if (is_array($value)) {
            return collect($value)
                ->map(fn ($item) => $this->safeConfig($item))
                ->all();
        }

        return is_scalar($value) || $value === null ? $value : null;
    }

    private function normalizeViewName(string $view): string
    {
        return trim(str_replace('/', '.', $view), '.');
    }

    private function safeHandle(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    private function safeSlug(string $value): string
    {
        return Str::slug(str_replace(['::', '.', '/'], '-', $value)) ?: 'bard-set';
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function blockNamespace(): string
    {
        return $this->safeSlug((string) config('statamic-gutenberg.bard_blocks.block_namespace', 'bard'));
    }

    private function enabled(): bool
    {
        return (bool) config('statamic-gutenberg.bard_blocks.enabled', true);
    }
}
