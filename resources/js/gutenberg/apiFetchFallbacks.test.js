import { describe, expect, it, vi } from 'vitest';
import {
    installStatamicApiFetchFallbacks,
    resolveStatamicApiFetchFallback,
} from './apiFetchFallbacks';

describe('Statamic Gutenberg apiFetch fallbacks', () => {
    it('returns standalone responses for read-only WordPress REST endpoints', () => {
        expect(resolveStatamicApiFetchFallback({ path: '/wp/v2/types?context=view' })).toEqual({});
        expect(resolveStatamicApiFetchFallback({ path: '/wp/v2/taxonomies?context=view' })).toEqual({});
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

        expect(result).toEqual({});
        expect(next).not.toHaveBeenCalled();
    });
});
