import { findRegisteredMediaPayload } from './serialization';

const INSTALLED_KEY = '__statamicGutenbergApiFetchFallbacksInstalled';

function methodFor(options = {}) {
    return (options.method || 'GET').toUpperCase();
}

function isReadOnly(options = {}) {
    return ['GET', 'HEAD', 'OPTIONS'].includes(methodFor(options));
}

function apiPath(options = {}) {
    const value = options.path || options.url || '';

    if (! value) {
        return '';
    }

    try {
        const url = new URL(value, currentOrigin());

        return `${url.pathname}${url.search}`;
    } catch {
        return value;
    }
}

function currentOrigin() {
    return typeof window !== 'undefined' && window.location?.origin
        ? window.location.origin
        : 'https://statamic.localhost';
}

function sameOriginRequestUrl(value) {
    const url = new URL(value, currentOrigin());

    if (typeof window !== 'undefined' && url.host === window.location.host) {
        url.protocol = window.location.protocol;
    }

    return url;
}

function statamicIconsUrl() {
    return typeof window !== 'undefined' ? window.StatamicGutenbergIconsUrl : null;
}

function statamicPatterns() {
    const payload = typeof window !== 'undefined' ? window.StatamicGutenbergPatterns : null;

    return payload && typeof payload === 'object' ? payload : {};
}

function patternReusableBlocks() {
    const payload = statamicPatterns();

    return Array.isArray(payload.reusableBlocks) ? payload.reusableBlocks : [];
}

function patternAllReusableBlocks() {
    const payload = statamicPatterns();

    return Array.isArray(payload.restReusableBlocks) ? payload.restReusableBlocks : patternReusableBlocks();
}

function patternCategories() {
    const payload = statamicPatterns();
    const categories = [
        payload.restBlockPatternCategories,
        payload.blockPatternCategories,
        payload.userPatternCategories,
    ].find((items) => Array.isArray(items) && items.length);

    return Array.isArray(categories)
        ? categories.map((category) => ({
            name: category.name || category.slug,
            label: category.label || category.name || category.slug,
            description: category.description || '',
        })).filter((category) => category.name)
        : [];
}

function mediaRecord(id) {
    const media = findRegisteredMediaPayload(id);

    if (! media) {
        return {
            id,
            alt_text: '',
            caption: { raw: '', rendered: '' },
            media_details: {},
            media_type: 'file',
            mime_type: '',
            source_url: '',
            title: { raw: '', rendered: '' },
            type: 'attachment',
        };
    }

    const title = media.title || media.filename || '';
    const caption = media.caption || '';

    return {
        ...media,
        id,
        alt_text: media.alt_text || media.alt || '',
        caption: { raw: caption, rendered: caption },
        media_details: media.media_details || {},
        media_type: media.media_type || media.type || 'file',
        mime_type: media.mime_type || media.mime || '',
        source_url: media.source_url || media.url || '',
        title: { raw: title, rendered: title },
        type: 'attachment',
    };
}

function patternCategoryTerms() {
    const payload = statamicPatterns();
    const categories = Array.isArray(payload.userPatternCategories) ? payload.userPatternCategories : [];

    return categories.map((category) => ({
        id: category.id,
        name: category.label || category.name || category.slug,
        slug: category.slug || category.name,
        description: category.description || '',
    })).filter((category) => category.slug);
}

function patternBlockPatterns() {
    const payload = statamicPatterns();
    const patterns = Array.isArray(payload.restBlockPatterns)
        ? payload.restBlockPatterns
        : payload.blockPatterns;

    return Array.isArray(patterns) ? patterns : [];
}

function findReusableBlock(id) {
    const numericId = Number.parseInt(id, 10);

    return patternAllReusableBlocks().find((block) => Number(block.id) === numericId) || null;
}

function wordpressPostTypes() {
    return {
        wp_block: {
            slug: 'wp_block',
            rest_base: 'blocks',
            rest_namespace: 'wp/v2',
            name: 'Patterns',
            labels: {
                name: 'Patterns',
                singular_name: 'Pattern',
            },
            taxonomies: ['wp_pattern_category'],
        },
    };
}

function wordpressTaxonomies() {
    return {
        wp_pattern_category: {
            slug: 'wp_pattern_category',
            rest_base: 'wp_pattern_category',
            rest_namespace: 'wp/v2',
            name: 'Pattern Categories',
            types: ['wp_block'],
        },
    };
}

async function fetchStatamicIcons() {
    const endpoint = statamicIconsUrl();

    if (! endpoint) {
        return [];
    }

    const response = await fetch(sameOriginRequestUrl(endpoint).toString(), {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
    const json = await response.json().catch(() => ({}));

    return Array.isArray(json.data) ? json.data : [];
}

function iconNameFromPath(path) {
    const match = path.match(/^\/wp\/v2\/icons\/(.+?)(?:\?|$)/);

    return match ? decodeURIComponent(match[1]) : null;
}

async function resolveStatamicIconsRequest(path) {
    const icons = await fetchStatamicIcons();
    const name = iconNameFromPath(path);

    if (! name) {
        return icons;
    }

    return icons.find((icon) => icon.name === name) || {};
}

function youtubeEmbedUrl(rawUrl) {
    if (! rawUrl) {
        return null;
    }

    try {
        const url = new URL(rawUrl);
        const host = url.hostname.replace(/^www\./, '');
        let id = '';

        if (host === 'youtu.be') {
            id = url.pathname.split('/').filter(Boolean)[0] || '';
        } else if (host === 'youtube.com' || host === 'youtube-nocookie.com') {
            if (url.pathname.startsWith('/embed/')) {
                id = url.pathname.split('/').filter(Boolean)[1] || '';
            } else if (url.pathname === '/watch') {
                id = url.searchParams.get('v') || '';
            } else if (url.pathname.startsWith('/shorts/')) {
                id = url.pathname.split('/').filter(Boolean)[1] || '';
            }
        }

        return id ? `https://www.youtube.com/embed/${encodeURIComponent(id)}` : null;
    } catch {
        return null;
    }
}

function fallbackForOembedProxy(path) {
    if (! /^\/oembed\/1\.0\/proxy(?:\?|$)/.test(path)) {
        return undefined;
    }

    const url = new URL(path, currentOrigin());
    const embed = youtubeEmbedUrl(url.searchParams.get('url'));

    if (! embed) {
        return {};
    }

    return {
        type: 'video',
        provider_name: 'YouTube',
        provider_url: 'https://www.youtube.com/',
        title: 'YouTube video',
        html: `<iframe src="${embed}" width="560" height="315" frameborder="0" allowfullscreen></iframe>`,
        width: 560,
        height: 315,
    };
}

function fallbackForKnownWordPressEndpoint(path) {
    if (/^\/wp\/v2\/icons(?:\/|\?|$)/.test(path)) {
        return resolveStatamicIconsRequest(path);
    }

    if (/^\/wp\/v2\/types(?:\?|$)/.test(path)) {
        return wordpressPostTypes();
    }

    if (/^\/wp\/v2\/taxonomies(?:\?|$)/.test(path)) {
        return wordpressTaxonomies();
    }

    if (/^\/wp\/v2\/block-patterns\/categories(?:\?|$)/.test(path)) {
        return patternCategories();
    }

    if (/^\/wp\/v2\/block-patterns\/patterns(?:\?|$)/.test(path)) {
        return patternBlockPatterns();
    }

    if (/^\/wp\/v2\/wp_pattern_category(?:\/|\?|$)/.test(path)) {
        return patternCategoryTerms();
    }

    if (/^\/wp\/v2\/blocks\/\d+(?:\?|$)/.test(path)) {
        const id = path.match(/^\/wp\/v2\/blocks\/(\d+)/)?.[1];

        return findReusableBlock(id) || {};
    }

    if (/^\/wp\/v2\/blocks(?:\?|$)/.test(path)) {
        return patternReusableBlocks();
    }

    if (/^\/wp\/v2\/settings(?:\?|$)/.test(path)) {
        return {};
    }

    if (/^\/wp\/v2\/users\/me(?:\?|$)/.test(path)) {
        return {
            id: 0,
            name: 'Statamic',
            slug: 'statamic',
            link: currentOrigin(),
        };
    }

    if (/^\/wp\/v2\/block-renderer\//.test(path)) {
        return { rendered: '' };
    }

    if (/^\/wp\/v2\/media\/\d+(?:\?|$)/.test(path)) {
        const id = Number(path.match(/^\/wp\/v2\/media\/(\d+)/)?.[1] || 0);

        return mediaRecord(id);
    }

    if (/^\/wp\/v2\/(?:media|search|themes|templates|template-parts|block-types|block-directory|pattern-directory|global-styles)(?:\/|\?|$)/.test(path)) {
        return [];
    }

    return undefined;
}

function genericReadOnlyFallback(path) {
    if (! path.startsWith('/wp/v2/')) {
        return undefined;
    }

    return /\/\d+(?:\?|$)/.test(path) ? {} : [];
}

export function resolveStatamicApiFetchFallback(options = {}) {
    if (! isReadOnly(options)) {
        return undefined;
    }

    const path = apiPath(options).replace(/^\/index\.php/, '');
    const oembedFallback = fallbackForOembedProxy(path);

    if (oembedFallback !== undefined) {
        return oembedFallback;
    }

    if (! path.startsWith('/wp/v2/')) {
        return undefined;
    }

    return fallbackForKnownWordPressEndpoint(path) ?? genericReadOnlyFallback(path);
}

function fallbackResponse(data) {
    return {
        ok: true,
        status: 200,
        headers: {
            get: (name) => {
                const lower = String(name || '').toLowerCase();

                if (lower === 'x-wp-total') {
                    return Array.isArray(data) ? String(data.length) : '1';
                }

                if (lower === 'x-wp-totalpages') {
                    return '1';
                }

                if (lower === 'allow') {
                    return 'GET';
                }

                return null;
            },
        },
        json: async () => data,
    };
}

export function installStatamicApiFetchFallbacks(apiFetch) {
    if (! apiFetch || typeof apiFetch.use !== 'function' || apiFetch[INSTALLED_KEY]) {
        return;
    }

    apiFetch.use((options, next) => {
        const fallback = resolveStatamicApiFetchFallback(options);

        if (fallback !== undefined) {
            return Promise.resolve(options?.parse === false ? fallbackResponse(fallback) : fallback);
        }

        return next(options);
    });

    apiFetch[INSTALLED_KEY] = true;
}
