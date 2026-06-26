<?php

namespace Amazingbv\StatamicGutenberg\Tests;

use Amazingbv\StatamicGutenberg\Patterns\PatternRepository;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

class PatternRepositoryTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    public function test_missing_pattern_collection_returns_empty_editor_payload(): void
    {
        config(['statamic-gutenberg.patterns.collection' => 'missing_patterns']);

        $payload = app(PatternRepository::class)->editorPayload();

        $this->assertSame([], $payload['reusableBlocks']);
        $this->assertSame([], $payload['userPatternCategories']);
        $this->assertSame([], $payload['blockPatterns']);
        $this->assertSame([], $payload['blockPatternCategories']);
    }

    public function test_it_maps_statamic_entries_to_wordpress_reusable_block_payloads(): void
    {
        $entry = new FakePatternEntry([
            'id' => 'pattern-entry-id',
            'slug' => 'hero-intro',
            'title' => 'Hero Intro',
            'content' => '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->',
            'sync_status' => 'unsynced',
            'gutenberg_pattern_categories' => ['hero_sections'],
        ]);

        $payload = app(PatternRepository::class)->reusableBlockPayload($entry);

        $this->assertIsInt($payload['id']);
        $this->assertSame('hero-intro', $payload['slug']);
        $this->assertSame('wp_block', $payload['type']);
        $this->assertSame('Hero Intro', $payload['title']['raw']);
        $this->assertSame('unsynced', $payload['wp_pattern_sync_status']);
        $this->assertSame('<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->', $payload['content']['raw']);
        $this->assertSame(['hero-sections'], $payload['categories']);
        $this->assertNotEmpty($payload['wp_pattern_category']);
    }

    public function test_it_falls_back_to_legacy_categories_field(): void
    {
        $entry = new FakePatternEntry([
            'slug' => 'legacy-categories',
            'title' => 'Legacy Categories',
            'categories' => ['legacy'],
        ]);

        $payload = app(PatternRepository::class)->blockPatternPayload($entry);

        $this->assertSame(['legacy'], $payload['categories']);
    }

    public function test_it_maps_unsynced_entries_to_registered_pattern_payloads(): void
    {
        $entry = new FakePatternEntry([
            'slug' => 'text-callout',
            'title' => 'Text Callout',
            'content' => '<!-- wp:paragraph --><p>Callout</p><!-- /wp:paragraph -->',
            'sync_status' => 'unsynced',
            'categories' => ['text'],
            'keywords' => ['intro', 'copy'],
            'viewport_width' => 640,
            'inserter' => false,
        ]);

        $payload = app(PatternRepository::class)->blockPatternPayload($entry);

        $this->assertSame('statamic/text-callout', $payload['name']);
        $this->assertSame('Text Callout', $payload['title']);
        $this->assertSame(['text'], $payload['categories']);
        $this->assertSame(['intro', 'copy'], $payload['keywords']);
        $this->assertSame(640, $payload['viewportWidth']);
        $this->assertFalse($payload['inserter']);
        $this->assertSame('theme', $payload['source']);
    }

    public function test_editor_payload_filters_unpublished_and_inserter_disabled_patterns(): void
    {
        Collection::make('gutenberg_patterns')->save();

        Entry::make()
            ->collection('gutenberg_patterns')
            ->id('visible-pattern')
            ->slug('visible')
            ->published(true)
            ->data([
                'title' => 'Visible',
                'content' => '<!-- wp:paragraph --><p>Visible</p><!-- /wp:paragraph -->',
                'sync_status' => 'unsynced',
            ])
            ->save();

        Entry::make()
            ->collection('gutenberg_patterns')
            ->id('hidden-pattern')
            ->slug('hidden')
            ->published(true)
            ->data([
                'title' => 'Hidden',
                'content' => '<!-- wp:paragraph --><p>Hidden</p><!-- /wp:paragraph -->',
                'sync_status' => 'unsynced',
                'inserter' => false,
            ])
            ->save();

        Entry::make()
            ->collection('gutenberg_patterns')
            ->id('draft-pattern')
            ->slug('draft')
            ->published(false)
            ->data([
                'title' => 'Draft',
                'content' => '<!-- wp:paragraph --><p>Draft</p><!-- /wp:paragraph -->',
                'sync_status' => 'unsynced',
            ])
            ->save();

        $repository = app(PatternRepository::class);
        $payload = $repository->editorPayload();

        $this->assertSame(['visible'], array_column($payload['reusableBlocks'], 'slug'));
        $this->assertEqualsCanonicalizing(['visible', 'hidden'], array_column($payload['restReusableBlocks'], 'slug'));
        $this->assertSame(['statamic/visible'], array_column($payload['blockPatterns'], 'name'));
        $this->assertSame(['statamic/visible'], array_column($payload['restBlockPatterns'], 'name'));
        $this->assertSame(['statamic/visible'], array_column($repository->blockPatterns(), 'name'));
    }

    public function test_editor_payload_filters_patterns_against_field_allowed_blocks(): void
    {
        Collection::make('gutenberg_patterns')->save();

        Entry::make()
            ->collection('gutenberg_patterns')
            ->id('paragraph-pattern')
            ->slug('paragraph-pattern')
            ->published(true)
            ->data([
                'title' => 'Paragraph Pattern',
                'content' => '<!-- wp:paragraph --><p>Allowed</p><!-- /wp:paragraph -->',
                'sync_status' => 'unsynced',
            ])
            ->save();

        Entry::make()
            ->collection('gutenberg_patterns')
            ->id('cover-pattern')
            ->slug('cover-pattern')
            ->published(true)
            ->data([
                'title' => 'Cover Pattern',
                'content' => '<!-- wp:cover --><div class="wp-block-cover"><!-- wp:paragraph --><p>Disallowed wrapper</p><!-- /wp:paragraph --></div><!-- /wp:cover -->',
                'sync_status' => 'unsynced',
            ])
            ->save();

        $payload = app(PatternRepository::class)->editorPayload(['core/paragraph']);

        $this->assertSame(['paragraph-pattern'], array_column($payload['reusableBlocks'], 'slug'));
        $this->assertSame(['paragraph-pattern'], array_column($payload['restReusableBlocks'], 'slug'));
        $this->assertSame(['statamic/paragraph-pattern'], array_column($payload['blockPatterns'], 'name'));
        $this->assertSame(['statamic/paragraph-pattern'], array_column($payload['restBlockPatterns'], 'name'));
    }

    public function test_editor_payload_exposes_pattern_categories_to_category_tabs(): void
    {
        Collection::make('gutenberg_patterns')->save();

        Entry::make()
            ->collection('gutenberg_patterns')
            ->id('categorized-pattern')
            ->slug('categorized')
            ->published(true)
            ->data([
                'title' => 'Categorized',
                'content' => '<!-- wp:paragraph --><p>Categorized</p><!-- /wp:paragraph -->',
                'sync_status' => 'synced',
                'gutenberg_pattern_categories' => ['wibra'],
            ])
            ->save();

        $payload = app(PatternRepository::class)->editorPayload();

        $this->assertSame(['wibra'], $payload['reusableBlocks'][0]['categories']);
        $this->assertSame(['statamic/categorized'], array_column($payload['blockPatterns'], 'name'));
        $this->assertSame('<!-- wp:block {"ref":'.$payload['reusableBlocks'][0]['id'].'} /-->', $payload['blockPatterns'][0]['content']);
        $this->assertSame(['wibra'], $payload['blockPatterns'][0]['categories']);
        $this->assertSame(['wibra'], array_column($payload['blockPatternCategories'], 'name'));
        $this->assertSame(['wibra'], array_column($payload['userPatternCategories'], 'name'));
    }
}

class FakePatternEntry
{
    public function __construct(private array $data)
    {
        //
    }

    public function id(): string
    {
        return $this->data['id'] ?? $this->slug();
    }

    public function slug(): string
    {
        return $this->data['slug'] ?? 'pattern';
    }

    public function title(): string
    {
        return $this->data['title'] ?? 'Pattern';
    }

    public function get(string $key, mixed $fallback = null): mixed
    {
        return $this->data[$key] ?? $fallback;
    }

    public function editUrl(): string
    {
        return '/cp/collections/gutenberg_patterns/entries/'.$this->slug();
    }
}
