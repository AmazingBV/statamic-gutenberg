import fs from 'node:fs';
import { describe, expect, it } from 'vitest';
import { normalizeBlockStyles, registerStatamicBlockStyles } from './blockStyles';

describe('Gutenberg block styles', () => {
    it('normalizes Statamic block style payloads', () => {
        expect(normalizeBlockStyles([
            {
                blocks: ['core/paragraph', 'core/paragraph', 'bad'],
                style: {
                    name: 'lead',
                    label: 'Lead',
                    isDefault: true,
                    source: 'statamic',
                    style: {
                        typography: {
                            fontWeight: '700',
                        },
                    },
                },
            },
            {
                blocks: [],
                style: {
                    name: 'ignored',
                },
            },
        ])).toEqual([
            {
                blocks: ['core/paragraph'],
                style: {
                    name: 'lead',
                    label: 'Lead',
                    isDefault: true,
                    source: 'statamic',
                },
            },
        ]);
    });

    it('registers payloads through the native Gutenberg block style API', () => {
        const source = fs.readFileSync('resources/js/gutenberg/blockStyles.js', 'utf8');
        const registered = registerStatamicBlockStyles([
            {
                blocks: ['core/paragraph'],
                style: {
                    name: 'sgb-test-lead',
                    label: 'Lead',
                    source: 'statamic',
                },
            },
        ]);

        expect(source).toContain("import { registerBlockStyle } from '@wordpress/blocks'");
        expect(source).toContain('registerBlockStyle(blocks.length === 1 ? blocks[0] : blocks, style)');
        expect(registered).toEqual([
            {
                blocks: ['core/paragraph'],
                style: {
                    name: 'sgb-test-lead',
                    label: 'Lead',
                    isDefault: false,
                    source: 'statamic',
                },
            },
        ]);
    });
});
