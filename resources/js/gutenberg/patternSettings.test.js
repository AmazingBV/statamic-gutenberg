import { describe, expect, it } from 'vitest';
import { applyPatternSettings, reusableBlocksForInserter } from './patternSettings';

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
});
