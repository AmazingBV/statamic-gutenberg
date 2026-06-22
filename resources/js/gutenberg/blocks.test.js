import fs from 'node:fs';
import { describe, expect, it } from 'vitest';
import {
    WIDE_FULL_ALIGNMENTS,
    withWideFullAlignSupport,
} from './blockSupport';

describe('registerGutenbergBlocks', () => {
    it('enables experimental and form core blocks before registering block-library blocks', () => {
        const source = fs.readFileSync('resources/js/gutenberg/blocks.jsx', 'utf8');
        const experimentsIndex = source.indexOf('window.__experimentalEnableBlockExperiments = true');
        const formsIndex = source.indexOf('window.__experimentalEnableFormBlocks = true');
        const registerIndex = source.indexOf('registerCoreBlocks();');

        expect(experimentsIndex).toBeGreaterThan(-1);
        expect(formsIndex).toBeGreaterThan(-1);
        expect(registerIndex).toBeGreaterThan(-1);
        expect(experimentsIndex).toBeLessThan(registerIndex);
        expect(formsIndex).toBeLessThan(registerIndex);
    });

    it('registers wide and full align support before core blocks are registered', () => {
        const source = fs.readFileSync('resources/js/gutenberg/blocks.jsx', 'utf8');
        const filterCallIndex = source.indexOf('registerStatamicBlockFilters();');
        const registerIndex = source.indexOf('registerCoreBlocks();');

        expect(filterCallIndex).toBeGreaterThan(-1);
        expect(source).toContain("from './blockSupport'");
        expect(source).toContain("'blocks.registerBlockType'");
        expect(source).toContain('withWideFullAlignSupport');
        expect(filterCallIndex).toBeLessThan(registerIndex);
    });

    it('merges wide and full align support into block settings', () => {
        expect(WIDE_FULL_ALIGNMENTS).toEqual(['wide', 'full']);
        expect(withWideFullAlignSupport({ supports: {} }).supports.align).toEqual(['wide', 'full']);
        expect(withWideFullAlignSupport({ supports: { align: 'center' } }).supports.align).toEqual(['center', 'wide', 'full']);
        expect(withWideFullAlignSupport({ supports: { align: ['left', 'wide'] } }).supports.align).toEqual(['left', 'wide', 'full']);

        const settings = withWideFullAlignSupport({ supports: { align: true } });
        expect(settings.supports.align).toBe(true);
        expect(settings.attributes.align).toEqual({
            type: 'string',
            enum: ['left', 'center', 'right', 'wide', 'full', ''],
        });
    });

    it('adds editor wrapper styles for group flex and grid layouts', () => {
        const source = fs.readFileSync('resources/js/gutenberg/blocks.jsx', 'utf8');

        expect(source).toContain("'editor.BlockListBlock'");
        expect(source).toContain('withStatamicLayoutWrapperStyles');
        expect(source).toContain('layout.type === \'grid\'');
        expect(source).toContain('style.gridTemplateColumns');
        expect(source).toContain('layout.type === \'flex\'');
    });
});
