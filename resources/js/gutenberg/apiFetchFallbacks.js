import { createMediaPayload, findRegisteredMediaPayload } from './serialization';

const INSTALLED_KEY = '__statamicGutenbergApiFetchFallbacksInstalled';
const FALLBACK_RESPONSE_KEY = '__statamicGutenbergFallbackResponse';

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

function statamicBlockRendererUrl() {
    return typeof window !== 'undefined' ? window.StatamicGutenbergBlockRendererUrl : null;
}

function statamicAssetsUrl() {
    return typeof window !== 'undefined' ? window.StatamicGutenbergAssetsUrl : null;
}

function statamicMediaUrl() {
    return typeof window !== 'undefined' ? window.StatamicGutenbergMediaUrl : null;
}

function statamicUploadUrl() {
    return typeof window !== 'undefined' ? window.StatamicGutenbergUploadUrl : null;
}

function statamicAssetsContainer() {
    return typeof window !== 'undefined' ? window.StatamicGutenbergAssetsContainer : null;
}

function statamicAllowedBlocks() {
    const blocks = typeof window !== 'undefined' ? window.StatamicGutenbergAllowedBlocks : null;

    return Array.isArray(blocks) ? blocks : [];
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

function mediaRecord(value) {
    const media = isPlainObject(value)
        ? createMediaPayload(value)
        : findRegisteredMediaPayload(value);
    const id = media?.id || Number(value || 0);

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
        id: media.id,
        alt_text: media.alt_text || media.alt || '',
        caption: { raw: caption, rendered: caption },
        description: { raw: media.description || '', rendered: media.description || '' },
        guid: { rendered: media.source_url || media.url || '' },
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

function isPlainObject(value) {
    return value && typeof value === 'object' && ! Array.isArray(value);
}

function blockRendererNameFromPath(path) {
    const match = path.match(/^\/wp\/v2\/block-renderer\/(.+?)(?:\?|$)/);

    return match ? decodeURIComponent(match[1]) : null;
}

function blockRendererAttributes(url, options = {}) {
    const dataAttributes = options.data?.attributes;

    if (isPlainObject(dataAttributes)) {
        return dataAttributes;
    }

    const rawAttributes = url.searchParams.get('attributes');

    if (rawAttributes) {
        try {
            const decoded = JSON.parse(rawAttributes);

            if (isPlainObject(decoded)) {
                return decoded;
            }
        } catch {
            return {};
        }
    }

    const attributes = {};

    url.searchParams.forEach((value, key) => {
        const match = key.match(/^attributes\[(.+)]$/);

        if (match) {
            attributes[match[1]] = value;
        }
    });

    return attributes;
}

function resolveBlockRendererRequest(path, options = {}) {
    const endpoint = statamicBlockRendererUrl();
    const name = blockRendererNameFromPath(path);

    if (! endpoint || ! name) {
        return undefined;
    }

    const url = new URL(path, currentOrigin());
    return fetch(sameOriginRequestUrl(endpoint).toString(), {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
            name,
            attributes: blockRendererAttributes(url, options),
            content: options.data?.content || url.searchParams.get('content') || '',
            allowed_blocks: statamicAllowedBlocks(),
        }),
    }).then((response) => {
        if (! response.ok) {
            throw new Error('Unable to render block preview.');
        }

        return response.json();
    });
}

function csrfToken() {
    return typeof document !== 'undefined'
        ? document.querySelector('meta[name="csrf-token"]')?.content || ''
        : '';
}

function parseNumericList(value) {
    return String(value || '')
        .split(',')
        .map((item) => Number.parseInt(item, 10))
        .filter((item) => Number.isFinite(item) && item > 0);
}

function statamicTypeFromWordPressQuery(url) {
    const type = url.searchParams.get('media_type') || url.searchParams.get('type') || '';
    const mime = url.searchParams.get('mime_type') || '';

    if (['image', 'audio', 'video', 'file'].includes(type)) {
        return type;
    }

    if (mime.startsWith('image/')) {
        return 'image';
    }

    if (mime.startsWith('audio/')) {
        return 'audio';
    }

    if (mime.startsWith('video/')) {
        return 'video';
    }

    return 'file';
}

function mediaFallbackResponse(data, headers = {}) {
    return {
        [FALLBACK_RESPONSE_KEY]: true,
        data,
        headers,
    };
}

function responseHeadersFromMediaJson(json, records) {
    return {
        'x-wp-total': String(json?.meta?.total ?? records.length),
        'x-wp-totalpages': String(json?.meta?.total_pages ?? 1),
        allow: 'GET, POST',
    };
}

async function fetchStatamicMediaList(path) {
    const endpoint = statamicAssetsUrl();

    if (! endpoint) {
        return mediaFallbackResponse([], {
            'x-wp-total': '0',
            'x-wp-totalpages': '1',
            allow: 'GET, POST',
        });
    }

    const wpUrl = new URL(path, currentOrigin());
    const url = sameOriginRequestUrl(endpoint);
    const search = wpUrl.searchParams.get('search') || wpUrl.searchParams.get('q') || '';
    const mediaType = statamicTypeFromWordPressQuery(wpUrl);
    const include = parseNumericList(wpUrl.searchParams.get('include'));
    const exclude = parseNumericList(wpUrl.searchParams.get('exclude'));

    url.searchParams.set('container', wpUrl.searchParams.get('statamic_container') || '*');
    url.searchParams.set('folder', wpUrl.searchParams.get('statamic_folder') || '/');
    url.searchParams.set('q', search);
    url.searchParams.set('type', mediaType);
    url.searchParams.set('page', wpUrl.searchParams.get('page') || '1');
    url.searchParams.set('per_page', wpUrl.searchParams.get('per_page') || '20');

    if (wpUrl.searchParams.get('mime_type')) {
        url.searchParams.append('mime_types[]', wpUrl.searchParams.get('mime_type'));
    }

    const response = await fetch(url.toString(), {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
    const json = await response.json().catch(() => ({}));

    if (! response.ok) {
        throw new Error(json?.message || 'Unable to load Statamic media.');
    }

    let records = (Array.isArray(json.data) ? json.data : []).map((asset) => mediaRecord(asset));

    if (include.length) {
        records = records.filter((record) => include.includes(Number(record.id)));
    }

    if (exclude.length) {
        records = records.filter((record) => ! exclude.includes(Number(record.id)));
    }

    return mediaFallbackResponse(records, responseHeadersFromMediaJson(json, records));
}

async function fetchStatamicMediaDetail(id) {
    const registered = findRegisteredMediaPayload(id);
    const endpoint = statamicMediaUrl();

    if (! endpoint || ! registered?.statamicId) {
        return mediaRecord(id);
    }

    const url = sameOriginRequestUrl(endpoint);
    url.searchParams.set('id', registered.statamicId);

    const response = await fetch(url.toString(), {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
    const json = await response.json().catch(() => ({}));

    if (! response.ok) {
        throw new Error(json?.message || 'Unable to load Statamic media.');
    }

    return mediaRecord(json.data || registered);
}

function formDataHas(formData, key) {
    return typeof formData?.has === 'function' && formData.has(key);
}

async function uploadStatamicMedia(options = {}) {
    const endpoint = statamicUploadUrl();

    if (! endpoint) {
        return undefined;
    }

    const hasFormData = typeof FormData !== 'undefined';
    if (! hasFormData) {
        return undefined;
    }

    const formData = hasFormData && options.body instanceof FormData ? options.body : new FormData();
    const data = isPlainObject(options.data) ? options.data : {};

    if (! formDataHas(formData, 'file') && data.file) {
        formData.append('file', data.file);
    }

    if (! formDataHas(formData, 'container')) {
        formData.append('container', data.container || statamicAssetsContainer() || 'assets');
    }

    if (! formDataHas(formData, 'type')) {
        formData.append('type', data.type || 'file');
    }

    if (! formDataHas(formData, 'folder')) {
        formData.append('folder', data.folder || '/');
    }

    const response = await fetch(sameOriginRequestUrl(endpoint).toString(), {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: formData,
    });
    const json = await response.json().catch(() => ({}));

    if (! response.ok) {
        throw new Error(json?.message || 'Unable to upload Statamic media.');
    }

    return mediaRecord(json.data || {});
}

function mediaUpdateData(options = {}) {
    if (isPlainObject(options.data)) {
        return options.data;
    }

    if (typeof options.body === 'string') {
        try {
            const decoded = JSON.parse(options.body);

            return isPlainObject(decoded) ? decoded : {};
        } catch {
            return {};
        }
    }

    return {};
}

async function updateStatamicMedia(id, options = {}) {
    const registered = findRegisteredMediaPayload(id);
    const endpoint = statamicMediaUrl();

    if (! endpoint || ! registered?.statamicId) {
        return mediaRecord(id);
    }

    const data = mediaUpdateData(options);
    const response = await fetch(sameOriginRequestUrl(endpoint).toString(), {
        method: 'PATCH',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
            id: registered.statamicId,
            alt_text: data.alt_text ?? data.alt,
            title: data.title,
            caption: data.caption,
        }),
    });
    const json = await response.json().catch(() => ({}));

    if (! response.ok) {
        throw new Error(json?.message || 'Unable to update Statamic media.');
    }

    return mediaRecord(json.data || registered);
}

function resolveMediaRequest(path, options = {}) {
    if (! /^\/wp\/v2\/media(?:\/|\?|$)/.test(path)) {
        return undefined;
    }

    const method = methodFor(options);
    const id = Number(path.match(/^\/wp\/v2\/media\/(\d+)(?:\/edit)?(?:\?|$)/)?.[1] || 0);

    if (['GET', 'HEAD', 'OPTIONS'].includes(method)) {
        return id ? fetchStatamicMediaDetail(id) : fetchStatamicMediaList(path);
    }

    if (['POST', 'PATCH', 'PUT'].includes(method)) {
        if (id) {
            return statamicMediaUrl() ? updateStatamicMedia(id, options) : undefined;
        }

        return statamicUploadUrl() ? uploadStatamicMedia(options) : undefined;
    }

    return undefined;
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

    if (/^\/wp\/v2\/(?:search|themes|templates|template-parts|block-types|block-directory|pattern-directory|global-styles)(?:\/|\?|$)/.test(path)) {
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
    const path = apiPath(options).replace(/^\/index\.php/, '');
    const blockRendererFallback = resolveBlockRendererRequest(path, options);

    if (blockRendererFallback !== undefined) {
        return blockRendererFallback;
    }

    const mediaFallback = resolveMediaRequest(path, options);

    if (mediaFallback !== undefined) {
        return mediaFallback;
    }

    if (! isReadOnly(options)) {
        return undefined;
    }

    const oembedFallback = fallbackForOembedProxy(path);

    if (oembedFallback !== undefined) {
        return oembedFallback;
    }

    if (! path.startsWith('/wp/v2/')) {
        return undefined;
    }

    return fallbackForKnownWordPressEndpoint(path) ?? genericReadOnlyFallback(path);
}

function fallbackResponse(data, headers = {}) {
    return {
        ok: true,
        status: 200,
        headers: {
            get: (name) => {
                const lower = String(name || '').toLowerCase();
                const value = headers[lower] ?? headers[name];

                if (value !== undefined) {
                    return String(value);
                }

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
            return Promise.resolve(fallback).then((data) => {
                if (data?.[FALLBACK_RESPONSE_KEY]) {
                    return options?.parse === false
                        ? fallbackResponse(data.data, data.headers)
                        : data.data;
                }

                return options?.parse === false ? fallbackResponse(data) : data;
            });
        }

        return next(options);
    });

    apiFetch[INSTALLED_KEY] = true;
}
