const INSTALLED_KEY = '__statamicGutenbergApiFetchFallbacksInstalled';

function methodFor(options = {}) {
    return (options.method || 'GET').toUpperCase();
}

function isReadOnly(options = {}) {
    return ['GET', 'HEAD'].includes(methodFor(options));
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
        return {};
    }

    if (/^\/wp\/v2\/taxonomies(?:\?|$)/.test(path)) {
        return {};
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

        return {
            id,
            alt_text: '',
            caption: { rendered: '' },
            media_details: {},
            source_url: '',
        };
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

export function installStatamicApiFetchFallbacks(apiFetch) {
    if (! apiFetch || typeof apiFetch.use !== 'function' || apiFetch[INSTALLED_KEY]) {
        return;
    }

    apiFetch.use((options, next) => {
        const fallback = resolveStatamicApiFetchFallback(options);

        if (fallback !== undefined) {
            return Promise.resolve(fallback);
        }

        return next(options);
    });

    apiFetch[INSTALLED_KEY] = true;
}
