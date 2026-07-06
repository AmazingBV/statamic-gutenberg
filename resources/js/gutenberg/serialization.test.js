import { describe, expect, it, beforeAll } from 'vitest';
import React from 'react';
import { getBlockType, registerBlockType } from '@wordpress/blocks';
import {
    attributesForAssetBlock,
    createImageBlock,
    createImageMedia,
    findRegisteredMediaPayload,
    isBlockAllowed,
    normalizeLegacyCoreMarkup,
    normalizeAllowedBlocks,
    parseSerialized,
    parseSerializedWithValidation,
    serializeBlocks,
    stripTransientMediaUrls,
    validateSerialized,
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
                statamicId: { type: 'string' },
                url: { type: 'string' },
                alt: { type: 'string' },
            },
            save: ({ attributes }) => React.createElement('img', {
                src: attributes.url,
                alt: attributes.alt || '',
            }),
        });
    }

    if (! getBlockType('core/spacer')) {
        registerBlockType('core/spacer', {
            apiVersion: 3,
            title: 'Spacer',
            category: 'design',
            attributes: {
                height: {
                    type: 'string',
                    default: '100px',
                },
            },
            save: ({ attributes }) => React.createElement('div', {
                style: { height: attributes.height || '100px' },
                'aria-hidden': 'true',
                className: 'wp-block-spacer',
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

    it('reports invalid Gutenberg block comment syntax without parsing blocks', () => {
        const missingClose = '<!-- wp:paragraph --><p>Hello</p>';
        const invalidJson = '<!-- wp:paragraph {"style": } --><p>Hello</p><!-- /wp:paragraph -->';

        expect(validateSerialized(missingClose)).toMatchObject({
            valid: false,
            message: 'Missing closing block "core/paragraph".',
        });
        expect(validateSerialized(invalidJson)).toMatchObject({
            valid: false,
            message: 'Block attributes must be valid JSON.',
        });
        expect(parseSerializedWithValidation(missingClose)).toMatchObject({
            blocks: [],
            valid: false,
        });
    });

    it('normalizes allowed blocks from preload meta first', () => {
        expect(normalizeAllowedBlocks({ allowed_blocks: ['core/quote'] }, { allowedBlocks: ['core/paragraph'] }))
            .toEqual(['core/paragraph']);
        expect(isBlockAllowed('core/image', ['core/paragraph'])).toBe(false);
        expect(isBlockAllowed('core/image', ['core/image'])).toBe(true);
    });

    it('preserves an explicit empty allowed block list', () => {
        expect(normalizeAllowedBlocks({ allowed_blocks: ['core/quote'] }, { allowedBlocks: [] }))
            .toEqual([]);
        expect(isBlockAllowed('core/image', [])).toBe(false);
        expect(isBlockAllowed('core/image')).toBe(true);
    });

    it('creates core image blocks from Statamic assets', () => {
        const block = createImageBlock({
            id: 'assets::hero.jpg',
            url: '/storage/assets/hero.jpg',
            alt: 'Hero',
            type: 'image',
        });

        expect(block.name).toBe('core/image');
        expect(block.attributes.url).toBe('/storage/assets/hero.jpg');
        expect(block.attributes.alt).toBe('Hero');
        expect(block.attributes.id).toEqual(expect.any(Number));
        expect(block.attributes.statamicId).toBe('assets::hero.jpg');
    });

    it('creates WordPress media payloads with stable numeric ids and Statamic ids', () => {
        const media = createImageMedia({
            id: 'assets::hero.jpg',
            url: '/storage/assets/hero.jpg',
            alt: 'Hero',
            title: 'Hero image',
            type: 'image',
        });
        const sameMedia = createImageMedia({
            id: 'assets::hero.jpg',
            url: '/storage/assets/hero.jpg',
            alt: 'Hero',
            title: 'Hero image',
            type: 'image',
        });

        expect(media.id).toEqual(expect.any(Number));
        expect(sameMedia.id).toBe(media.id);
        expect(media.statamicId).toBe('assets::hero.jpg');
        expect(findRegisteredMediaPayload(media.id)).toEqual(sameMedia);
        expect(media).toMatchObject({
            url: '/storage/assets/hero.jpg',
            source_url: '/storage/assets/hero.jpg',
            alt: 'Hero',
            alt_text: 'Hero',
            title: 'Hero image',
            caption: '',
            media_type: 'image',
            type: 'image',
        });
    });

    it('preserves Statamic media metadata from WordPress-compatible asset payloads', () => {
        const media = createImageMedia({
            id: 'assets::nested/hero.jpg',
            wpId: 987,
            url: '/storage/assets/nested/hero.jpg',
            alt_text: 'Hero alt',
            title: { raw: 'Hero title' },
            caption: { raw: 'Hero caption' },
            type: 'image',
            mime_type: 'image/jpeg',
            container: 'assets',
            folder: 'nested',
            width: 1200,
            height: 800,
            filesize: 123456,
            media_details: {
                sizes: {
                    full: {
                        source_url: '/storage/assets/nested/hero.jpg',
                        width: 1200,
                        height: 800,
                    },
                },
            },
        });

        expect(media).toMatchObject({
            id: 987,
            statamicId: 'assets::nested/hero.jpg',
            container: 'assets',
            folder: 'nested',
            alt_text: 'Hero alt',
            title: 'Hero title',
            caption: 'Hero caption',
            mime_type: 'image/jpeg',
            width: 1200,
            height: 800,
            filesize: 123456,
        });
        expect(findRegisteredMediaPayload(987)).toEqual(media);
    });

    it('adds core media ids to direct asset block attributes', () => {
        const asset = {
            id: 'assets::hero.jpg',
            url: '/storage/assets/hero.jpg',
            alt: 'Hero',
            title: 'Hero image',
            type: 'image',
        };
        const imageAttributes = attributesForAssetBlock('core/image', asset);
        const coverAttributes = attributesForAssetBlock('core/cover', asset);
        const mediaTextAttributes = attributesForAssetBlock('core/media-text', asset);

        expect(imageAttributes.id).toEqual(expect.any(Number));
        expect(coverAttributes.id).toBe(imageAttributes.id);
        expect(mediaTextAttributes.mediaId).toBe(imageAttributes.id);
        expect(imageAttributes.statamicId).toBe('assets::hero.jpg');
        expect(coverAttributes.statamicId).toBe('assets::hero.jpg');
        expect(mediaTextAttributes.statamicId).toBe('assets::hero.jpg');
    });

    it('enables controls for inserted video asset blocks', () => {
        const attributes = attributesForAssetBlock('core/video', {
            id: 'assets::movie.mp4',
            url: '/storage/assets/movie.mp4',
            type: 'video',
        });

        expect(attributes.controls).toBe(true);
    });

    it('strips transient blob media urls before saved content is parsed or serialized', () => {
        const input = '<!-- wp:cover {"url":"blob:https://site.test/temp-id"} --><div class="wp-block-cover"><img src="blob:https://site.test/temp-id"><p>Cover</p></div><!-- /wp:cover -->';
        const stripped = stripTransientMediaUrls(input);

        expect(stripped).toContain('"url":""');
        expect(stripped).not.toContain('blob:');
    });

    it('normalizes legacy spacer markup without the default height style', () => {
        const input = '<!-- wp:spacer {"style":{"layout":{}}} --><div aria-hidden="true" class="wp-block-spacer"></div><!-- /wp:spacer -->';
        const normalized = normalizeLegacyCoreMarkup(input);

        expect(normalized).toContain('<div style="height:100px" aria-hidden="true" class="wp-block-spacer">');
        expect(serializeBlocks(parseSerialized(input))).toContain('style="height:100px"');

        expect(normalizeLegacyCoreMarkup('<!-- wp:spacer {"height":"2rem"} --><div aria-hidden="true" class="wp-block-spacer"></div><!-- /wp:spacer -->'))
            .not.toContain('height:100px');
    });
});
