import fs from 'node:fs';
import { describe, expect, it } from 'vitest';
import {
    controlKindForField,
    formatBardFieldValue,
    normalizeBardBlocks,
    parseBardFieldValue,
} from './bardBlockValues';

describe('bard block editor integration', () => {
    it('registers bard blocks from preload metadata before parsing saved content', () => {
        const editor = fs.readFileSync('resources/js/gutenberg/GutenbergEditor.jsx', 'utf8');
        const bardBlocks = fs.readFileSync('resources/js/gutenberg/bardBlocks.js', 'utf8');

        expect(editor).toContain("import { prepareBardBlockRegistry } from './bardBlocks'");
        expect(editor).toContain('prepareBardBlockRegistry(meta?.bardBlocks');
        expect(editor).toContain('previewUrl: meta?.bardPreviewUrl');
        expect(editor).toContain('debounceMs: meta?.bardPreviewDebounceMs');
        expect(editor).toContain('window.StatamicGutenbergBardBlocks');
        expect(bardBlocks).toContain('registerBlockType(block.name');
        expect(bardBlocks).toContain('setCategories([');
        expect(bardBlocks).toContain('InspectorControls');
        expect(bardBlocks).toContain('dangerouslySetInnerHTML');
        expect(bardBlocks).toContain('window.StatamicGutenbergOpenMediaPicker');
        expect(bardBlocks).toContain('setAttributes({');
        expect(bardBlocks).toContain('values: {');
    });

    it('normalizes valid bard block payloads only', () => {
        expect(normalizeBardBlocks([
            { name: 'bard/hero', metadata: {}, fields: [] },
            { name: 'bard/broken', metadata: null, fields: [] },
            { metadata: {}, fields: [] },
        ])).toEqual([
            { name: 'bard/hero', metadata: {}, fields: [] },
        ]);
    });

    it('maps known fieldtypes to native controls and unknown fieldtypes to textarea or json fallback', () => {
        expect(controlKindForField({ type: 'text' })).toBe('text');
        expect(controlKindForField({ type: 'slug' })).toBe('text');
        expect(controlKindForField({ type: 'textarea' })).toBe('textarea');
        expect(controlKindForField({ type: 'toggle' })).toBe('toggle');
        expect(controlKindForField({ type: 'select' })).toBe('select');
        expect(controlKindForField({ type: 'button_group' })).toBe('select');
        expect(controlKindForField({ type: 'integer' })).toBe('number');
        expect(controlKindForField({ type: 'float' })).toBe('number');
        expect(controlKindForField({ type: 'range' })).toBe('number');
        expect(controlKindForField({ type: 'assets' })).toBe('assets');
        expect(controlKindForField({ type: 'entries' })).toBe('json');
        expect(controlKindForField({ type: 'unknown' })).toBe('textarea');
        expect(controlKindForField({ type: 'unknown', default: { keep: true } })).toBe('json');
        expect(controlKindForField({ type: 'unknown' }, { keep: true })).toBe('json');
        expect(controlKindForField({ type: 'unknown' }, ['one'])).toBe('json');
    });

    it('formats objects as pretty json and preserves strings when json parsing fails', () => {
        expect(formatBardFieldValue({ title: 'Hero', items: ['one'] })).toBe('{\n  "title": "Hero",\n  "items": [\n    "one"\n  ]\n}');
        expect(parseBardFieldValue('{"title":"Hero"}', {})).toEqual({ title: 'Hero' });
        expect(parseBardFieldValue('not json', {})).toBe('not json');
        expect(parseBardFieldValue('[1,2]', '')).toEqual([1, 2]);
        expect(parseBardFieldValue('plain text', '')).toBe('plain text');
    });
});
