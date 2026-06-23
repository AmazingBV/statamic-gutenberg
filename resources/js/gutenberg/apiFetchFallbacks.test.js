import { describe, expect, it, vi } from 'vitest';
import {
    installStatamicApiFetchFallbacks,
    resolveStatamicApiFetchFallback,
} from './apiFetchFallbacks';

describe('Statamic Gutenberg apiFetch fallbacks', () => {
    it('returns standalone responses for read-only WordPress REST endpoints', () => {
        expect(resolveStatamicApiFetchFallback({ path: '/wp/v2/types?context=view' })).toHaveProperty('wp_block.rest_base', 'blocks');
        expect(resolveStatamicApiFetchFallback({ path: '/wp/v2/taxonomies?context=view' })).toHaveProperty('wp_pattern_category.rest_base', 'wp_pattern_category');
        expect(resolveStatamicApiFetchFallback({ path: '/wp/v2/media?per_page=20' })).toEqual([]);
        expect(resolveStatamicApiFetchFallback({ path: '/wp/v2/users/me?context=edit' })).toMatchObject({
            id: 0,
            name: 'Statamic',
        });
    });

    it('does not intercept Statamic requests or mutating WordPress requests', () => {
        expect(resolveStatamicApiFetchFallback({ path: '/cp/assets' })).toBeUndefined();
        expect(resolveStatamicApiFetchFallback({ path: '/wp/v2/media', method: 'POST' })).toBeUndefined();
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
                { name: 'statamic/hero', title: 'Hero', content: '<!-- wp:paragraph --><p>Hero</p><!-- /wp:paragraph -->' },
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
        expect(resolveStatamicApiFetchFallback({ path: '/wp/v2/block-patterns/patterns' })).toHaveLength(1);

        delete window.StatamicGutenbergPatterns;
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
