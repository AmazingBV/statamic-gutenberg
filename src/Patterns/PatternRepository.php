<?php

namespace Amazingbv\StatamicGutenberg\Patterns;

use Illuminate\Support\Collection as SupportCollection;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Throwable;

class PatternRepository
{
    public function editorPayload(): array
    {
        $entries = $this->publishedEntries();
        $inserterEntries = $entries
            ->filter(fn ($entry) => $this->inserterEnabled($entry))
            ->values();

        return [
            'reusableBlocks' => $inserterEntries
                ->map(fn ($entry) => $this->reusableBlockPayload($entry))
                ->values()
                ->all(),
            'restReusableBlocks' => $entries
                ->map(fn ($entry) => $this->reusableBlockPayload($entry))
                ->values()
                ->all(),
            'userPatternCategories' => $this->categoryPayloads($inserterEntries),
            'blockPatterns' => $inserterEntries
                ->map(fn ($entry) => $this->editorBlockPatternPayload($entry))
                ->values()
                ->all(),
            'blockPatternCategories' => $this->blockPatternCategories($inserterEntries),
            'restBlockPatterns' => $this->blockPatterns(),
            'restBlockPatternCategories' => $this->blockPatternCategories($inserterEntries),
        ];
    }

    public function reusableBlocks(): array
    {
        return $this->editorPayload()['reusableBlocks'];
    }

    public function reusableBlock(int|string $id): ?array
    {
        $id = (int) $id;

        return $this->publishedEntries()
            ->map(fn ($entry) => $this->reusableBlockPayload($entry))
            ->first(fn (array $block) => (int) $block['id'] === $id);
    }

    public function blockPatterns(): array
    {
        return $this->publishedEntries()
            ->filter(fn ($entry) => $this->inserterEnabled($entry))
            ->filter(fn ($entry) => $this->syncStatus($entry) === 'unsynced')
            ->map(fn ($entry) => $this->blockPatternPayload($entry))
            ->values()
            ->all();
    }

    public function blockPatternCategories(?SupportCollection $entries = null): array
    {
        return collect($this->categoryPayloads($entries ?? $this->publishedEntries()))
            ->map(fn (array $category) => [
                'name' => $category['name'],
                'label' => $category['label'],
                'description' => $category['description'] ?? '',
            ])
            ->values()
            ->all();
    }

    public function findRenderablePattern(int|string|null $id): ?array
    {
        if (! $id) {
            return null;
        }

        $block = $this->reusableBlock($id);

        if (! $block || ($block['wp_pattern_sync_status'] ?? '') === 'unsynced') {
            return null;
        }

        return [
            'id' => $block['id'],
            'slug' => $block['slug'],
            'title' => $block['title']['raw'] ?? '',
            'content' => $block['content']['raw'] ?? '',
        ];
    }

    public function reusableBlockPayload(mixed $entry): array
    {
        $id = $this->numericId($entry);
        $slug = $this->slug($entry);
        $title = $this->title($entry);
        $modified = $this->modifiedDate($entry);
        $categories = $this->categorySlugs($entry);

        return [
            'id' => $id,
            'date' => $modified,
            'date_gmt' => $modified,
            'modified' => $modified,
            'modified_gmt' => $modified,
            'slug' => $slug,
            'status' => 'publish',
            'type' => 'wp_block',
            'link' => $this->editUrl($entry),
            'title' => ['raw' => $title, 'rendered' => e($title)],
            'content' => [
                'raw' => $this->content($entry),
                'rendered' => '',
                'protected' => false,
                'block_version' => 1,
            ],
            'categories' => $categories,
            'wp_pattern_category' => collect($categories)
                ->map(fn (string $slug) => $this->categoryId($slug))
                ->values()
                ->all(),
            'wp_pattern_sync_status' => $this->syncStatus($entry) === 'unsynced' ? 'unsynced' : '',
            'meta' => [],
            'template' => '',
        ];
    }

    public function blockPatternPayload(mixed $entry): array
    {
        return [
            'name' => 'statamic/'.$this->slug($entry),
            'title' => $this->title($entry),
            'content' => $this->content($entry),
            'description' => (string) $this->field($entry, $this->config('description_field', 'description'), ''),
            'viewportWidth' => $this->nullableInt($this->field($entry, $this->config('viewport_width_field', 'viewport_width'))),
            'inserter' => $this->inserterEnabled($entry),
            'categories' => $this->categorySlugs($entry),
            'keywords' => $this->stringList($this->field($entry, $this->config('keywords_field', 'keywords'), [])),
            'blockTypes' => $this->stringList($this->field($entry, $this->config('block_types_field', 'block_types'), [])),
            'postTypes' => $this->stringList($this->field($entry, $this->config('post_types_field', 'post_types'), [])),
            'templateTypes' => $this->stringList($this->field($entry, $this->config('template_types_field', 'template_types'), [])),
            'source' => 'theme',
        ];
    }

    private function editorBlockPatternPayload(mixed $entry): array
    {
        $payload = $this->blockPatternPayload($entry);

        if ($this->syncStatus($entry) === 'synced') {
            $payload['content'] = sprintf(
                '<!-- wp:block {"ref":%d} /-->',
                $this->numericId($entry)
            );
        }

        return $payload;
    }

    private function publishedEntries(): SupportCollection
    {
        $collection = $this->config('collection', 'gutenberg_patterns');

        try {
            if (! Collection::findByHandle($collection)) {
                return collect();
            }

            return collect(Entry::query()
                ->where('collection', $collection)
                ->whereStatus('published')
                ->get())
                ->values();
        } catch (Throwable) {
            return collect();
        }
    }

    private function categoryPayloads(SupportCollection $entries): array
    {
        $slugs = $entries
            ->flatMap(fn ($entry) => $this->categorySlugs($entry))
            ->unique()
            ->values();
        $terms = $this->termLabels();

        return $slugs
            ->map(fn (string $slug) => [
                'id' => $this->categoryId($slug),
                'name' => $slug,
                'slug' => $slug,
                'label' => $terms[$slug]['label'] ?? $this->labelFromSlug($slug),
                'description' => $terms[$slug]['description'] ?? '',
            ])
            ->values()
            ->all();
    }

    private function termLabels(): array
    {
        $taxonomy = $this->config('taxonomy', 'gutenberg_pattern_categories');

        try {
            if (! Taxonomy::findByHandle($taxonomy)) {
                return [];
            }

            return collect(Term::query()->where('taxonomy', $taxonomy)->get())
                ->mapWithKeys(fn ($term) => [
                    $this->termSlug($term) => [
                        'label' => $this->termTitle($term),
                        'description' => (string) $this->field($term, 'description', ''),
                    ],
                ])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function numericId(mixed $entry): int
    {
        $source = (string) ($this->objectValue($entry, 'id') ?: $this->slug($entry));
        $id = hexdec(substr(hash('sha256', $source), 0, 12));

        return max(1, (int) $id);
    }

    private function categoryId(string $slug): int
    {
        $id = hexdec(substr(hash('sha256', $slug), 0, 10));

        return max(1, (int) $id);
    }

    private function syncStatus(mixed $entry): string
    {
        $value = strtolower((string) $this->field($entry, $this->config('sync_status_field', 'sync_status'), 'synced'));

        return $value === 'unsynced' ? 'unsynced' : 'synced';
    }

    private function inserterEnabled(mixed $entry): bool
    {
        $value = $this->field($entry, $this->config('inserter_field', 'inserter'), true);

        return ! in_array($value, [false, 0, '0', 'false', 'off', 'no'], true);
    }

    private function content(mixed $entry): string
    {
        return (string) $this->field($entry, $this->config('content_field', 'content'), '');
    }

    private function categorySlugs(mixed $entry): array
    {
        $field = $this->config('categories_field', $this->config('taxonomy', 'gutenberg_pattern_categories'));
        $taxonomy = $this->config('taxonomy', 'gutenberg_pattern_categories');
        $value = $this->stringList($this->field($entry, $field, []));

        if ($value === [] && $field !== $taxonomy) {
            $value = $this->stringList($this->field($entry, $taxonomy, []));
        }

        if ($value === [] && $field !== 'categories') {
            $value = $this->stringList($this->field($entry, 'categories', []));
        }

        return collect($value)
            ->map(fn (string $slug) => str($slug)->slug()->toString())
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function stringList(mixed $value): array
    {
        if ($value instanceof SupportCollection) {
            $value = $value->all();
        }

        if (is_string($value)) {
            $value = str_contains($value, ',') ? explode(',', $value) : [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(function ($item) {
                if (is_array($item)) {
                    return $item['value'] ?? $item['slug'] ?? $item['id'] ?? $item['title'] ?? '';
                }

                if (is_object($item)) {
                    return $this->objectValue($item, 'slug')
                        ?: $this->objectValue($item, 'id')
                        ?: $this->objectValue($item, 'title')
                        ?: '';
                }

                return $item;
            })
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function slug(mixed $entry): string
    {
        return (string) ($this->objectValue($entry, 'slug') ?: str($this->title($entry))->slug()->toString());
    }

    private function termSlug(mixed $term): string
    {
        return (string) ($this->objectValue($term, 'slug') ?: '');
    }

    private function title(mixed $entry): string
    {
        return (string) ($this->objectValue($entry, 'title') ?: $this->field($entry, 'title', 'Untitled'));
    }

    private function termTitle(mixed $term): string
    {
        return (string) ($this->objectValue($term, 'title') ?: $this->labelFromSlug($this->termSlug($term)));
    }

    private function modifiedDate(mixed $entry): string
    {
        try {
            $modified = $this->objectValue($entry, 'lastModified') ?: $this->objectValue($entry, 'date');

            if ($modified && method_exists($modified, 'toIso8601String')) {
                return $modified->toIso8601String();
            }
        } catch (Throwable) {
            //
        }

        return now()->toIso8601String();
    }

    private function editUrl(mixed $entry): string
    {
        try {
            if (is_object($entry) && method_exists($entry, 'editUrl')) {
                return (string) $entry->editUrl();
            }
        } catch (Throwable) {
            //
        }

        return '';
    }

    private function labelFromSlug(string $slug): string
    {
        return str($slug)->replace(['-', '_'], ' ')->title()->toString();
    }

    private function field(mixed $entry, string $field, mixed $fallback = null): mixed
    {
        try {
            if (is_object($entry) && method_exists($entry, 'get')) {
                return $entry->get($field, $fallback);
            }

            if (is_array($entry)) {
                return $entry[$field] ?? $fallback;
            }
        } catch (Throwable) {
            //
        }

        return $fallback;
    }

    private function objectValue(mixed $object, string $method): mixed
    {
        try {
            return is_object($object) && method_exists($object, $method) ? $object->{$method}() : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function config(string $key, mixed $fallback = null): mixed
    {
        return config("statamic-gutenberg.patterns.{$key}", $fallback);
    }
}
