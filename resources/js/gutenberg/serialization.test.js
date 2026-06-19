import { describe, expect, it, beforeAll } from 'vitest';
import React from 'react';
import { getBlockType, registerBlockType } from '@wordpress/blocks';
import {
    createImageBlock,
    createImageMedia,
    isBlockAllowed,
    normalizeAllowedBlocks,
    parseSerialized,
    serializeBlocks,
    stripTransientMediaUrls,
} from './serialization';

beforeAll(() => {
    if (! getBlockType('core/paragraph')) {
        registerBlockType('core/paragraph', {
            apiVersion: 3,
            title: 'Paragraph',
            category: 'text',
            attributes: {
                content: {
                    type: 'string',
                    source: 'html',
                    selector: 'p',
                },
            },
            save: ({ attributes }) => React.createElement('p', null, attributes.content),
        });
    }

    if (! getBlockType('core/image')) {
        registerBlockType('core/image', {
            apiVersion: 3,
            title: 'Image',
            category: 'media',
            attributes: {
                id: { type: 'string' },
                url: { type: 'string' },
                alt: { type: 'string' },
            },
            save: ({ attributes }) => React.createElement('img', {
                src: attributes.url,
                alt: attributes.alt || '',
            }),
        });
    }
});

describe('Gutenberg serialization helpers', () => {
    it('parses and serializes paragraph content', () => {
        const input = '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->';
        const blocks = parseSerialized(input);

        expect(blocks).toHaveLength(1);
        expect(blocks[0].name).toBe('core/paragraph');
        expect(parseSerialized(serializeBlocks(blocks))[0].name).toBe('core/paragraph');
    });

    it('normalizes allowed blocks from preload meta first', () => {
        expect(normalizeAllowedBlocks({ allowed_blocks: ['core/quote'] }, { allowedBlocks: ['core/paragraph'] }))
            .toEqual(['core/paragraph']);
        expect(isBlockAllowed('core/image', ['core/paragraph'])).toBe(false);
        expect(isBlockAllowed('core/image', ['core/image'])).toBe(true);
    });

    it('creates core image blocks from Statamic assets', () => {
        const block = createImageBlock({
            id: 'assets::hero.jpg',
            url: '/storage/assets/hero.jpg',
            alt: 'Hero',
        });

        expect(block.name).toBe('core/image');
        expect(block.attributes.url).toBe('/storage/assets/hero.jpg');
        expect(block.attributes.alt).toBe('Hero');
        expect(block.attributes.id).toBeUndefined();
    });

    it('creates WordPress media payloads without Statamic string ids', () => {
        const media = createImageMedia({
            id: 'assets::hero.jpg',
            url: '/storage/assets/hero.jpg',
            alt: 'Hero',
            title: 'Hero image',
        });

        expect(media).toEqual({
            url: '/storage/assets/hero.jpg',
            source_url: '/storage/assets/hero.jpg',
            alt: 'Hero',
            title: 'Hero image',
            caption: '',
        });
    });

    it('strips transient blob media urls before saved content is parsed or serialized', () => {
        const input = '<!-- wp:cover {"url":"blob:https://site.test/temp-id"} --><div class="wp-block-cover"><img src="blob:https://site.test/temp-id"><p>Cover</p></div><!-- /wp:cover -->';
        const stripped = stripTransientMediaUrls(input);

        expect(stripped).toContain('"url":""');
        expect(stripped).not.toContain('blob:');
    });
});
