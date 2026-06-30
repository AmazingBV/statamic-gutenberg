import { createBlock, parse, serialize } from '@wordpress/blocks';

const TRANSIENT_MEDIA_URL_PATTERN = /blob:[^"'\\\s<>]+/gi;
const MEDIA_REGISTRY_KEY = '__statamicGutenbergMediaPayloads';
const FALLBACK_MEDIA_REGISTRY = new Map();
const BLOCK_COMMENT_PATTERN = /<!--\s*(\/?)wp:([a-z0-9_/-]+)([\s\S]*?)-->/gi;

function normalizeBlockName(name) {
    return name.includes('/') ? name : `core/${name}`;
}

function validateBlockCommentAttributes(rawAttributes) {
    const attributes = rawAttributes.trim().replace(/\/\s*$/, '').trim();

    if (! attributes) {
        return null;
    }

    if (! attributes.startsWith('{')) {
        return 'Block attributes must be valid JSON.';
    }

    try {
        JSON.parse(attributes);
    } catch {
        return 'Block attributes must be valid JSON.';
    }

    return null;
}

export function validateSerialized(value) {
    if (! value || typeof value !== 'string') {
        return { valid: true, message: '' };
    }

    const content = stripTransientMediaUrls(value);
    const expectedCommentCount = (content.match(/<!--\s*\/?wp:/gi) || []).length;
    const stack = [];
    let matchedCommentCount = 0;
    BLOCK_COMMENT_PATTERN.lastIndex = 0;
    let match = BLOCK_COMMENT_PATTERN.exec(content);

    while (match) {
        matchedCommentCount += 1;

        const isClosing = match[1] === '/';
        const name = normalizeBlockName(match[2]);
        const body = match[3] || '';
        const isSelfClosing = ! isClosing && /\/\s*$/.test(body.trim());

        if (isClosing) {
            const opened = stack.pop();

            if (opened !== name) {
                return {
                    valid: false,
                    message: `Unexpected closing block "${name}".`,
                };
            }
        } else {
            const attributeError = validateBlockCommentAttributes(body);

            if (attributeError) {
                return {
                    valid: false,
                    message: attributeError,
                };
            }

            if (! isSelfClosing) {
                stack.push(name);
            }
        }

        match = BLOCK_COMMENT_PATTERN.exec(content);
    }

    if (matchedCommentCount !== expectedCommentCount) {
        return {
            valid: false,
            message: 'Block comment syntax is incomplete.',
        };
    }

    if (stack.length) {
        return {
            valid: false,
            message: `Missing closing block "${stack[stack.length - 1]}".`,
        };
    }

    return { valid: true, message: '' };
}

export function parseSerializedWithValidation(value) {
    const validation = validateSerialized(value);

    if (! validation.valid) {
        return {
            blocks: [],
            ...validation,
        };
    }

    try {
        return {
            blocks: parse(stripTransientMediaUrls(value)),
            valid: true,
            message: '',
        };
    } catch (error) {
        console.warn('Unable to parse block editor content.', error);

        return {
            blocks: [],
            valid: false,
            message: 'Unable to parse block editor content.',
        };
    }
}

export function parseSerialized(value) {
    if (! value || typeof value !== 'string') {
        return [];
    }

    return parseSerializedWithValidation(value).blocks;
}

export function serializeBlocks(blocks) {
    try {
        return stripTransientMediaUrls(serialize(blocks || []));
    } catch (error) {
        console.warn('Unable to serialize block editor blocks.', error);

        return '';
    }
}

export function stripTransientMediaUrls(value) {
    if (! value || typeof value !== 'string') {
        return '';
    }

    return value
        .replace(/"((?:url|src|poster))"\s*:\s*"blob:[^"]*"/gi, '"$1":""')
        .replace(/\s(?:src|href|poster)=["']blob:[^"']*["']/gi, '')
        .replace(/url\(\s*["']?blob:[^)]+?\)/gi, 'none')
        .replace(TRANSIENT_MEDIA_URL_PATTERN, '');
}

export function normalizeAllowedBlocks(config = {}, meta = {}) {
    const allowed = meta.allowedBlocks || config.allowed_blocks || [];

    return Array.isArray(allowed)
        ? allowed.filter(Boolean)
        : [];
}

export function isBlockAllowed(name, allowedBlocks) {
    if (! Array.isArray(allowedBlocks) || allowedBlocks.length === 0) {
        return true;
    }

    return allowedBlocks.includes(name);
}

export function createImageBlock(asset) {
    return createBlock('core/image', attributesForAssetBlock('core/image', asset));
}

export function createImageMedia(asset) {
    return createMediaPayload(asset);
}

function numericMediaId(value) {
    if (typeof value === 'number' && Number.isFinite(value) && value > 0) {
        return Math.trunc(value);
    }

    if (typeof value === 'string' && /^\d+$/.test(value)) {
        const numeric = Number(value);

        return Number.isFinite(numeric) && numeric > 0 ? numeric : null;
    }

    return null;
}

function stableNumericMediaId(value) {
    const source = String(value || '');

    if (! source) {
        return null;
    }

    let hash = 2166136261;

    for (let index = 0; index < source.length; index += 1) {
        hash ^= source.charCodeAt(index);
        hash = Math.imul(hash, 16777619);
    }

    return (hash >>> 0) % 2147480000 || 1;
}

function mediaRegistry() {
    if (typeof window === 'undefined') {
        return FALLBACK_MEDIA_REGISTRY;
    }

    if (! (window[MEDIA_REGISTRY_KEY] instanceof Map)) {
        window[MEDIA_REGISTRY_KEY] = new Map();
    }

    return window[MEDIA_REGISTRY_KEY];
}

function registerMediaPayload(payload) {
    if (! payload?.id) {
        return payload;
    }

    mediaRegistry().set(Number(payload.id), payload);

    return payload;
}

export function findRegisteredMediaPayload(id) {
    return mediaRegistry().get(Number(id)) || null;
}

export function createMediaPayload(asset = {}) {
    const mediaType = mediaTypeForAsset(asset);
    const url = asset.url || asset.source_url || '';
    const title = asset.title || asset.filename || '';
    const statamicId = asset.statamicId || asset.id || asset.path || url || title || '';
    const id = numericMediaId(asset.wpId || asset.wordpressId || asset.id)
        || stableNumericMediaId(statamicId);
    const imageSizes = mediaType === 'image'
        ? (asset.sizes || {
            full: { url },
            large: { url },
        })
        : {};
    const mediaDetailSizes = mediaType === 'image'
        ? (asset.media_details?.sizes || {
            full: { source_url: url },
            large: { source_url: url },
        })
        : {};

    return registerMediaPayload({
        id,
        url,
        source_url: url,
        link: asset.link || url,
        statamicId,
        alt: asset.alt || '',
        alt_text: asset.alt_text || asset.alt || '',
        title,
        caption: asset.caption || '',
        filename: asset.filename || title,
        mime: asset.mime || asset.mime_type || '',
        mime_type: asset.mime_type || asset.mime || '',
        type: mediaType,
        media_type: mediaType,
        sizes: imageSizes,
        media_details: {
            ...(asset.media_details || {}),
            sizes: mediaDetailSizes,
        },
    });
}

export function createAssetBlock(asset) {
    const media = createMediaPayload(asset);

    if (media.media_type === 'audio') {
        return createBlock('core/audio', attributesForAssetBlock('core/audio', asset));
    }

    if (media.media_type === 'video') {
        return createBlock('core/video', attributesForAssetBlock('core/video', asset));
    }

    if (media.media_type === 'image') {
        return createImageBlock(asset);
    }

    return createBlock('core/file', attributesForAssetBlock('core/file', asset));
}

export function attributesForAssetBlock(blockName, asset = {}) {
    const media = createMediaPayload(asset);
    const url = media.url;
    const title = media.title || media.filename || '';

    switch (blockName) {
        case 'core/audio':
            return {
                id: media.id,
                src: url,
                caption: media.caption || '',
            };

        case 'core/cover':
            return {
                id: media.id,
                url,
                alt: media.alt || '',
                backgroundType: media.media_type === 'video' ? 'video' : 'image',
                useFeaturedImage: false,
            };

        case 'core/file':
            return {
                id: media.id,
                href: url,
                fileName: title,
                textLinkHref: url,
                downloadButtonText: 'Download',
            };

        case 'core/media-text':
            return {
                mediaId: media.id,
                mediaUrl: url,
                mediaAlt: media.alt || '',
                mediaType: media.media_type === 'video' ? 'video' : 'image',
                useFeaturedImage: false,
            };

        case 'core/video':
            return {
                id: media.id,
                src: url,
                controls: true,
                caption: media.caption || '',
            };

        case 'core/image':
        default:
            return {
                id: media.id,
                url,
                alt: media.alt || '',
                caption: media.caption || '',
            };
    }
}

export function mediaTypeForAsset(asset = {}) {
    return asset.media_type || asset.type || 'file';
}
