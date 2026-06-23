import fs from 'node:fs';
import { describe, expect, it } from 'vitest';
import {
    addTextAlignSaveProps,
    TEXT_ALIGNMENTS,
    TEXT_FORMATTING_BLOCKS,
    WIDE_FULL_ALIGNMENTS,
    textAlignClassName,
    withStatamicBlockSupport,
    withTextFormattingSupport,
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

    it('registers Statamic block support before core blocks are registered', () => {
        const source = fs.readFileSync('resources/js/gutenberg/blocks.jsx', 'utf8');
        const filterCallIndex = source.indexOf('registerStatamicBlockFilters();');
        const registerIndex = source.indexOf('registerCoreBlocks();');

        expect(filterCallIndex).toBeGreaterThan(-1);
        expect(source).toContain("from './blockSupport'");
        expect(source).toContain("'blocks.registerBlockType'");
        expect(source).toContain('withStatamicBlockSupport');
        expect(source).toContain("'editor.BlockEdit'");
        expect(source).toContain('withStatamicTextAlignmentControls');
        expect(source).toContain("'blocks.getSaveContent.extraProps'");
        expect(source).toContain('addTextAlignSaveProps');
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

    it('adds paragraph and heading text alignment and color support', () => {
        expect(TEXT_FORMATTING_BLOCKS).toEqual(['core/paragraph', 'core/heading']);
        expect(TEXT_ALIGNMENTS).toEqual(['left', 'center', 'right', 'justify']);

        const settings = withTextFormattingSupport({
            attributes: {},
            supports: {
                typography: { fontSize: true, textAlign: true },
                color: { gradients: true },
            },
        }, 'core/paragraph');

        expect(settings.supports.typography).toMatchObject({
            fontSize: true,
            textAlign: ['justify'],
        });
        expect(settings.supports.color).toMatchObject({
            gradients: true,
            text: true,
        });
        expect(settings.attributes.style).toEqual({ type: 'object' });
        expect(withTextFormattingSupport({ supports: {} }, 'core/image').supports).toEqual({});
    });

    it('combines align, text formatting and text align save props', () => {
        const settings = withStatamicBlockSupport({ supports: { align: ['wide'] }, attributes: {} }, 'core/heading');

        expect(settings.supports.align).toEqual(['wide', 'full']);
        expect(settings.supports.typography.textAlign).toEqual(['justify']);
        expect(settings.supports.color.text).toBe(true);
        expect(textAlignClassName({ style: { typography: { textAlign: 'justify' } } })).toBe('has-text-align-justify');
        expect(textAlignClassName({ style: { typography: { textAlign: 'invalid' } } })).toBe('');

        expect(addTextAlignSaveProps(
            { className: 'wp-block-heading' },
            { name: 'core/heading' },
            { style: { typography: { textAlign: 'right' } } },
        )).toEqual({
            className: 'wp-block-heading has-text-align-right',
        });

        expect(addTextAlignSaveProps(
            { className: 'wp-block-image' },
            { name: 'core/image' },
            { style: { typography: { textAlign: 'justify' } } },
        )).toEqual({
            className: 'wp-block-image',
        });
    });

    it('adds editor wrapper styles for group flex and grid layouts', () => {
        const source = fs.readFileSync('resources/js/gutenberg/blocks.jsx', 'utf8');

        expect(source).toContain("'editor.BlockListBlock'");
        expect(source).toContain('withStatamicLayoutWrapperStyles');
        expect(source).toContain('layout.type === \'grid\'');
        expect(source).toContain('style.gridTemplateColumns');
        expect(source).toContain('layout.type === \'flex\'');
        expect(source).toContain('style.textAlign = textAlign');
    });

    it('feeds Statamic patterns into native Gutenberg editor settings', () => {
        const source = fs.readFileSync('resources/js/gutenberg/GutenbergEditor.jsx', 'utf8');
        const fieldtypeSource = fs.readFileSync('resources/js/components/fieldtypes/StatamicGutenberg.vue', 'utf8');
        const patternSettingsSource = fs.readFileSync('resources/js/gutenberg/patternSettings.js', 'utf8');

        expect(source).toContain('window.StatamicGutenbergPatterns = patternSettings');
        expect(source).toContain('window.StatamicGutenbergPatterns');
        expect(source).toContain("import { applyPatternSettings } from './patternSettings'");
        expect(fieldtypeSource).toContain('meta.patternsUrl');
        expect(fieldtypeSource).toContain('window.StatamicGutenbergPatterns = meta.patterns');
        expect(patternSettingsSource).toContain('__experimentalReusableBlocks');
        expect(patternSettingsSource).toContain('__experimentalUserPatternCategories');
        expect(patternSettingsSource).toContain('__experimentalBlockPatterns');
        expect(patternSettingsSource).toContain('__experimentalBlockPatternCategories');
        expect(patternSettingsSource).toContain('reusableBlocksForInserter');
    });
});
