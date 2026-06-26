import { describe, expect, it } from 'vitest';
import { applyPatternSettings, filterPatternPayload, reusableBlocksForInserter } from './patternSettings';

describe('Gutenberg pattern settings', () => {
    it('normalizes reusable block categories to slugs for the native inserter', () => {
        const reusableBlocks = reusableBlocksForInserter([
            {
                id: 123,
                title: { raw: 'Header' },
                wp_pattern_category: [601046541979],
            },
            {
                id: 456,
                title: { raw: 'Footer' },
                categories: ['layout'],
                wp_pattern_category: [601046541979],
            },
        ], [
            {
                id: 601046541979,
                name: 'wibra',
                slug: 'wibra',
                label: 'Wibra',
            },
        ]);

        expect(reusableBlocks[0].wp_pattern_category).toEqual(['wibra']);
        expect(reusableBlocks[1].wp_pattern_category).toEqual(['layout', 'wibra']);
    });

    it('feeds normalized reusable blocks and user categories into editor settings', () => {
        const settings = applyPatternSettings({ allowedBlockTypes: true }, {
            reusableBlocks: [
                {
                    id: 123,
                    title: { raw: 'Header' },
                    wp_pattern_category: [10],
                },
            ],
            userPatternCategories: [
                {
                    id: 10,
                    name: 'wibra',
                    slug: 'wibra',
                    label: 'Wibra',
                },
            ],
        });

        expect(settings.allowedBlockTypes).toBe(true);
        expect(settings.__experimentalReusableBlocks[0].wp_pattern_category).toEqual(['wibra']);
        expect(settings.__experimentalUserPatternCategories).toEqual([
            {
                id: 10,
                name: 'wibra',
                slug: 'wibra',
                label: 'Wibra',
            },
        ]);
    });

    it('filters pattern payloads against allowed blocks', () => {
        const payload = {
            reusableBlocks: [
                {
                    id: 1,
                    slug: 'paragraph',
                    categories: ['text'],
                    content: { raw: '<!-- wp:paragraph --><p>Allowed</p><!-- /wp:paragraph -->' },
                },
                {
                    id: 2,
                    slug: 'image',
                    categories: ['media'],
                    content: { raw: '<!-- wp:image --><figure class="wp-block-image"></figure><!-- /wp:image -->' },
                },
            ],
            blockPatterns: [
                {
                    name: 'statamic/paragraph',
                    categories: ['text'],
                    content: '<!-- wp:paragraph --><p>Allowed</p><!-- /wp:paragraph -->',
                },
                {
                    name: 'statamic/image',
                    categories: ['media'],
                    content: '<!-- wp:image --><figure class="wp-block-image"></figure><!-- /wp:image -->',
                },
            ],
            restBlockPatterns: [
                {
                    name: 'statamic/paragraph',
                    categories: ['text'],
                    content: '<!-- wp:paragraph --><p>Allowed</p><!-- /wp:paragraph -->',
                },
                {
                    name: 'statamic/image',
                    categories: ['media'],
                    content: '<!-- wp:image --><figure class="wp-block-image"></figure><!-- /wp:image -->',
                },
            ],
            blockPatternCategories: [
                { name: 'text', label: 'Text' },
                { name: 'media', label: 'Media' },
            ],
            userPatternCategories: [
                { id: 10, name: 'text', label: 'Text' },
                { id: 20, name: 'media', label: 'Media' },
            ],
        };

        const filtered = filterPatternPayload(payload, ['core/paragraph']);

        expect(filtered.reusableBlocks.map((block) => block.slug)).toEqual(['paragraph']);
        expect(filtered.blockPatterns.map((pattern) => pattern.name)).toEqual(['statamic/paragraph']);
        expect(filtered.restBlockPatterns.map((pattern) => pattern.name)).toEqual(['statamic/paragraph']);
        expect(filtered.blockPatternCategories.map((category) => category.name)).toEqual(['text']);
        expect(filtered.userPatternCategories.map((category) => category.name)).toEqual(['text']);
    });

    it('filters nested and synced pattern content by resolving reusable block refs', () => {
        const payload = {
            reusableBlocks: [
                {
                    id: 123,
                    slug: 'synced-ok',
                    categories: ['text'],
                    content: {
                        raw: '<!-- wp:group --><div class="wp-block-group"><!-- wp:paragraph --><p>Nested</p><!-- /wp:paragraph --></div><!-- /wp:group -->',
                    },
                },
                {
                    id: 456,
                    slug: 'synced-disallowed',
                    categories: ['media'],
                    content: {
                        raw: '<!-- wp:group --><div class="wp-block-group"><!-- wp:image --><figure class="wp-block-image"></figure><!-- /wp:image --></div><!-- /wp:group -->',
                    },
                },
            ],
            blockPatterns: [
                {
                    name: 'statamic/synced-ok',
                    categories: ['text'],
                    content: '<!-- wp:block {"ref":123} /-->',
                },
                {
                    name: 'statamic/synced-disallowed',
                    categories: ['media'],
                    content: '<!-- wp:block {"ref":456} /-->',
                },
            ],
        };

        const filtered = filterPatternPayload(payload, ['core/block', 'core/group', 'core/paragraph']);

        expect(filtered.reusableBlocks.map((block) => block.slug)).toEqual(['synced-ok']);
        expect(filtered.blockPatterns.map((pattern) => pattern.name)).toEqual(['statamic/synced-ok']);
    });

    it('does not filter patterns when no allowed block list is provided', () => {
        const payload = {
            blockPatterns: [
                {
                    name: 'statamic/image',
                    content: '<!-- wp:image --><figure class="wp-block-image"></figure><!-- /wp:image -->',
                },
            ],
        };

        expect(filterPatternPayload(payload, []).blockPatterns).toHaveLength(1);
        expect(filterPatternPayload(payload, true).blockPatterns).toHaveLength(1);
    });
});
