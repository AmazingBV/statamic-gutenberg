import fs from 'node:fs';
import { describe, expect, it } from 'vitest';

describe('custom block loading', () => {
    it('loads custom block assets before rendering the editor', () => {
        const editor = fs.readFileSync('resources/js/gutenberg/GutenbergEditor.jsx', 'utf8');
        const loader = fs.readFileSync('resources/js/gutenberg/customBlocks.js', 'utf8');

        expect(editor).toContain("import { loadCustomBlockAssets, normalizeCustomBlocks } from './customBlocks'");
        expect(editor).toContain('normalizeCustomBlocks(meta?.customBlocks)');
        expect(editor).toContain('loadCustomBlockAssets(customBlocks)');
        expect(editor).toContain('Loading custom blocks...');
        expect(loader).toContain('export function normalizeCustomBlocks');
        expect(loader).toContain("typeof block.name === 'string'");
        expect(loader).toContain('window.wp = wp');
        expect(loader).toContain('window.ReactJSXRuntime');
        expect(loader).toContain('wrapBlocksApi(blockApi)');
        expect(loader).toContain('mergeBlockSettings(customMetadata, settings)');
        expect(loader).toContain('registerFallbackBlocks(items)');
        expect(loader).toContain('blocks.registerBlockType(block.name');
        expect(loader).toContain('window.StatamicGutenbergCustomBlocks');
    });
});
