import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    installStatamicApiFetchFallbacks,
    resolveStatamicApiFetchFallback,
} from './apiFetchFallbacks';
import { createMediaPayload, parseSerialized } from './serialization';

describe('Statamic Gutenberg apiFetch fallbacks', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
        delete window.StatamicGutenbergAssetsUrl;
        delete window.StatamicGutenbergUploadUrl;
        delete window.StatamicGutenbergMediaUrl;
        delete window.StatamicGutenbergAssetsContainer;
        delete window.StatamicGutenbergBlockRendererUrl;
        delete window.StatamicGutenbergAllowedBlocks;
        delete window.StatamicGutenbergPatterns;
    });

    it('returns standalone responses for read-only WordPress REST endpoints', () => {
        expect(resolveStatamicApiFetchFallback({ path: '/wp/v2/types?context=view' })).toHaveProperty('wp_block.rest_base', 'blocks');
        expect(resolveStatamicApiFetchFallback({ path: '/wp/v2/taxonomies?context=view' })).toHaveProperty('wp_pattern_category.rest_base', 'wp_pattern_category');
        expect(resolveStatamicApiFetchFallback({ path: '/wp/v2/users/me?context=edit' })).toMatchObject({
            id: 0,
            name: 'Statamic',
        });
    });

    it('does not intercept Statamic requests or mutating WordPress requests', () => {
        expect(resolveStatamicApiFetchFallback({ path: '/cp/assets' })).toBeUndefined();
        expect(resolveStatamicApiFetchFallback({ path: '/wp/v2/media', method: 'POST' })).toBeUndefined();
    });

    it('proxies WordPress media list requests to Statamic assets', async () => {
        window.StatamicGutenbergAssetsUrl = '/cp/amazingbv/statamic-gutenberg/assets';
        const fetch = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({
                data: [
                    {
                        id: 'assets::hero.jpg',
                        wpId: 123,
                        url: '/storage/assets/hero.jpg',
                        title: 'Hero',
                        alt: 'Hero alt',
                        type: 'image',
                        mime_type: 'image/jpeg',
                        container: 'assets',
                    },
                ],
                meta: {
                    total: 1,
                    total_pages: 1,
                },
            }),
        });
        vi.stubGlobal('fetch', fetch);

        const use = vi.fn();
        const apiFetch = { use };
        installStatamicApiFetchFallbacks(apiFetch);
        const middleware = use.mock.calls[0][0];

        const result = await middleware({ path: '/wp/v2/media?search=hero&media_type=image&per_page=20' }, vi.fn());

        expect(fetch).toHaveBeenCalledWith(
            expect.stringContaining('/cp/amazingbv/statamic-gutenberg/assets'),
            expect.objectContaining({
                headers: expect.objectContaining({
                    Accept: 'application/json',
                }),
            }),
        );
        expect(fetch.mock.calls[0][0]).toContain('container=*');
        expect(fetch.mock.calls[0][0]).toContain('q=hero');
        expect(result).toHaveLength(1);
        expect(result[0]).toMatchObject({
            id: 123,
            statamicId: 'assets::hero.jpg',
            source_url: '/storage/assets/hero.jpg',
            alt_text: 'Hero alt',
            title: { raw: 'Hero', rendered: 'Hero' },
            media_type: 'image',
            type: 'attachment',
        });

        const response = await middleware({ path: '/wp/v2/media?per_page=20', parse: false }, vi.fn());

        expect(response.headers.get('x-wp-total')).toBe('1');
        expect(response.headers.get('x-wp-totalpages')).toBe('1');
    });

    it('serves registered Statamic media through WordPress media records', async () => {
        const media = createMediaPayload({
            id: 'assets::video.mp4',
            url: '/storage/assets/video.mp4',
            title: 'Video',
            caption: 'Caption',
            type: 'video',
            mime_type: 'video/mp4',
        });

        await expect(resolveStatamicApiFetchFallback({ path: `/wp/v2/media/${media.id}?context=edit` })).resolves.toMatchObject({
            id: media.id,
            statamicId: 'assets::video.mp4',
            source_url: '/storage/assets/video.mp4',
            media_type: 'video',
            mime_type: 'video/mp4',
            title: { raw: 'Video', rendered: 'Video' },
            caption: { raw: 'Caption', rendered: 'Caption' },
            type: 'attachment',
        });
    });

    it('hydrates persisted Statamic media identities when serialized content is reopened', async () => {
        parseSerialized([
            '<!-- wp:image {"id":12345,"statamicId":"assets::hero.jpg","url":"/storage/assets/hero.jpg","alt":"Hero"} --><figure class="wp-block-image"><img src="/storage/assets/hero.jpg" alt="Hero"></figure><!-- /wp:image -->',
            '<!-- wp:cover {"id":12346,"statamicId":"assets::cover.jpg","url":"/storage/assets/cover.jpg","alt":"Cover"} /-->',
            '<!-- wp:audio {"id":12347,"statamicId":"assets::podcast.mp3","src":"/storage/assets/podcast.mp3","caption":"Episode"} /-->',
            '<!-- wp:file {"id":12348,"statamicId":"assets::brochure.pdf","href":"/storage/assets/brochure.pdf","fileName":"Brochure.pdf"} /-->',
            '<!-- wp:media-text {"mediaId":12349,"statamicId":"assets::media.jpg","mediaUrl":"/storage/assets/media.jpg","mediaAlt":"Media","mediaType":"image"} --><div class="wp-block-media-text"><figure class="wp-block-media-text__media"><img src="/storage/assets/media.jpg" alt="Media"></figure><div class="wp-block-media-text__content"></div></div><!-- /wp:media-text -->',
            '<!-- wp:video {"id":12350,"statamicId":"assets::movie.mp4","src":"/storage/assets/movie.mp4","caption":"Movie"} /-->',
        ].join(''));

        await expect(resolveStatamicApiFetchFallback({ path: '/wp/v2/media/12345?context=edit' })).resolves.toMatchObject({
            id: 12345,
            statamicId: 'assets::hero.jpg',
            source_url: '/storage/assets/hero.jpg',
            alt_text: 'Hero',
            media_type: 'image',
        });
        await expect(resolveStatamicApiFetchFallback({ path: '/wp/v2/media/12346?context=edit' })).resolves.toMatchObject({
            id: 12346,
            statamicId: 'assets::cover.jpg',
            source_url: '/storage/assets/cover.jpg',
            alt_text: 'Cover',
            media_type: 'image',
        });
        await expect(resolveStatamicApiFetchFallback({ path: '/wp/v2/media/12347?context=edit' })).resolves.toMatchObject({
            id: 12347,
            statamicId: 'assets::podcast.mp3',
            source_url: '/storage/assets/podcast.mp3',
            caption: { raw: 'Episode', rendered: 'Episode' },
            media_type: 'audio',
        });
        await expect(resolveStatamicApiFetchFallback({ path: '/wp/v2/media/12348?context=edit' })).resolves.toMatchObject({
            id: 12348,
            statamicId: 'assets::brochure.pdf',
            source_url: '/storage/assets/brochure.pdf',
            title: { raw: 'Brochure.pdf', rendered: 'Brochure.pdf' },
            media_type: 'file',
        });
        await expect(resolveStatamicApiFetchFallback({ path: '/wp/v2/media/12349?context=edit' })).resolves.toMatchObject({
            id: 12349,
            statamicId: 'assets::media.jpg',
            source_url: '/storage/assets/media.jpg',
            alt_text: 'Media',
            media_type: 'image',
        });
        await expect(resolveStatamicApiFetchFallback({ path: '/wp/v2/media/12350?context=edit' })).resolves.toMatchObject({
            id: 12350,
            statamicId: 'assets::movie.mp4',
            source_url: '/storage/assets/movie.mp4',
            caption: { raw: 'Movie', rendered: 'Movie' },
            media_type: 'video',
        });
    });

    it('installs one middleware that resolves fallback requests before hitting the network', async () => {
        const use = vi.fn();
        const apiFetch = { use };

        installStatamicApiFetchFallbacks(apiFetch);
        installStatamicApiFetchFallbacks(apiFetch);

        expect(use).toHaveBeenCalledTimes(1);

        const middleware = use.mock.calls[0][0];
        const next = vi.fn();
        const result = await middleware({ path: '/wp/v2/types?context=view' }, next);

        expect(result).toHaveProperty('wp_block.rest_base', 'blocks');
        expect(next).not.toHaveBeenCalled();
    });

    it('proxies block renderer requests to the Statamic preview endpoint', async () => {
        window.StatamicGutenbergBlockRendererUrl = '/cp/amazingbv/statamic-gutenberg/block-renderer';
        window.StatamicGutenbergAllowedBlocks = ['core/paragraph'];
        const fetch = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ rendered: '<section>Preview</section>' }),
        });
        vi.stubGlobal('fetch', fetch);

        await expect(resolveStatamicApiFetchFallback({
            path: '/wp/v2/block-renderer/amazing/card?context=edit&attributes=%7B%22heading%22%3A%22Preview%22%7D',
        })).resolves.toEqual({ rendered: '<section>Preview</section>' });

        expect(fetch).toHaveBeenCalledWith(
            expect.stringContaining('/cp/amazingbv/statamic-gutenberg/block-renderer'),
            expect.objectContaining({
                method: 'POST',
                body: JSON.stringify({
                    name: 'amazing/card',
                    attributes: { heading: 'Preview' },
                    content: '',
                    allowed_blocks: ['core/paragraph'],
                }),
            }),
        );

        vi.unstubAllGlobals();
        delete window.StatamicGutenbergBlockRendererUrl;
        delete window.StatamicGutenbergAllowedBlocks;
    });

    it('proxies WordPress media metadata updates to Statamic assets', async () => {
        const media = createMediaPayload({
            id: 'assets::hero.jpg',
            url: '/storage/assets/hero.jpg',
            title: 'Hero',
            type: 'image',
        });
        window.StatamicGutenbergMediaUrl = '/cp/amazingbv/statamic-gutenberg/assets/media';
        const fetch = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({
                data: {
                    id: 'assets::hero.jpg',
                    wpId: media.id,
                    url: '/storage/assets/hero.jpg',
                    title: 'Updated title',
                    alt_text: 'Updated alt',
                    caption: 'Updated caption',
                    type: 'image',
                },
            }),
        });
        vi.stubGlobal('fetch', fetch);

        const result = await resolveStatamicApiFetchFallback({
            path: `/wp/v2/media/${media.id}`,
            method: 'POST',
            data: {
                alt_text: 'Updated alt',
                title: { raw: 'Updated title' },
                caption: { raw: 'Updated caption' },
            },
        });

        expect(fetch).toHaveBeenCalledWith(
            expect.stringContaining('/cp/amazingbv/statamic-gutenberg/assets/media'),
            expect.objectContaining({
                method: 'PATCH',
                body: JSON.stringify({
                    id: 'assets::hero.jpg',
                    alt_text: 'Updated alt',
                    title: { raw: 'Updated title' },
                    caption: { raw: 'Updated caption' },
                }),
            }),
        );
        expect(result).toMatchObject({
            id: media.id,
            statamicId: 'assets::hero.jpg',
            alt_text: 'Updated alt',
            title: { raw: 'Updated title', rendered: 'Updated title' },
            caption: { raw: 'Updated caption', rendered: 'Updated caption' },
        });
    });

    it('proxies WordPress media uploads to the Statamic upload endpoint', async () => {
        window.StatamicGutenbergUploadUrl = '/cp/amazingbv/statamic-gutenberg/assets';
        window.StatamicGutenbergAssetsContainer = 'assets';
        const fetch = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({
                data: {
                    id: 'assets::upload.jpg',
                    wpId: 456,
                    url: '/storage/assets/upload.jpg',
                    title: 'Upload',
                    type: 'image',
                },
            }),
        });
        vi.stubGlobal('fetch', fetch);

        const result = await resolveStatamicApiFetchFallback({
            path: '/wp/v2/media',
            method: 'POST',
            body: new FormData(),
        });

        expect(fetch).toHaveBeenCalledWith(
            expect.stringContaining('/cp/amazingbv/statamic-gutenberg/assets'),
            expect.objectContaining({
                method: 'POST',
                body: expect.any(FormData),
            }),
        );
        expect(result).toMatchObject({
            id: 456,
            statamicId: 'assets::upload.jpg',
            source_url: '/storage/assets/upload.jpg',
            media_type: 'image',
        });
    });

    it('serves Statamic patterns through WordPress-compatible endpoints', async () => {
        window.StatamicGutenbergPatterns = {
            reusableBlocks: [
                {
                    id: 123,
                    slug: 'hero',
                    type: 'wp_block',
                    title: { raw: 'Hero' },
                    content: { raw: '<!-- wp:paragraph --><p>Hero</p><!-- /wp:paragraph -->' },
                    wp_pattern_sync_status: '',
                },
            ],
            restReusableBlocks: [
                {
                    id: 123,
                    slug: 'hero',
                    type: 'wp_block',
                    title: { raw: 'Hero' },
                    content: { raw: '<!-- wp:paragraph --><p>Hero</p><!-- /wp:paragraph -->' },
                    wp_pattern_sync_status: '',
                },
                {
                    id: 999,
                    slug: 'hidden',
                    type: 'wp_block',
                    title: { raw: 'Hidden' },
                    content: { raw: '<!-- wp:paragraph --><p>Hidden</p><!-- /wp:paragraph -->' },
                    wp_pattern_sync_status: '',
                },
            ],
            userPatternCategories: [
                { id: 10, name: 'hero', slug: 'hero', label: 'Hero', description: 'Hero patterns' },
            ],
            restBlockPatterns: [
                { name: 'statamic/example', title: 'Hero', content: '<!-- wp:paragraph --><p>Hero</p><!-- /wp:paragraph -->' },
                { name: 'statamic/synced', title: 'Synced', content: '<!-- wp:block {"ref":123} /-->' },
            ],
        };

        expect(resolveStatamicApiFetchFallback({ path: '/wp/v2/blocks/123?context=edit' })).toMatchObject({
            id: 123,
            slug: 'hero',
        });
        expect(resolveStatamicApiFetchFallback({ path: '/wp/v2/blocks/999?context=edit' })).toMatchObject({
            id: 999,
            slug: 'hidden',
        });
        expect(resolveStatamicApiFetchFallback({ path: '/wp/v2/blocks?context=edit' })).toHaveLength(1);
        expect(resolveStatamicApiFetchFallback({ path: '/wp/v2/block-patterns/categories' })).toEqual([
            { name: 'hero', label: 'Hero', description: 'Hero patterns' },
        ]);
        expect(resolveStatamicApiFetchFallback({ path: '/wp/v2/block-patterns/patterns' })).toHaveLength(2);
        expect(resolveStatamicApiFetchFallback({ path: '/wp/v2/block-patterns/patterns' })[1]).toMatchObject({
            name: 'statamic/synced',
            content: '<!-- wp:block {"ref":123} /-->',
        });
    });

    it('returns a response-like object for core-data parse false requests', async () => {
        const use = vi.fn();
        const apiFetch = { use };

        installStatamicApiFetchFallbacks(apiFetch);

        const middleware = use.mock.calls[0][0];
        const response = await middleware({ path: '/wp/v2/types?context=view', parse: false }, vi.fn());

        expect(response.status).toBe(200);
        expect(response.headers.get('allow')).toBe('GET');
        await expect(response.json()).resolves.toHaveProperty('wp_block.rest_base', 'blocks');
    });
});
