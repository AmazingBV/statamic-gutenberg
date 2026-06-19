import { createBlock, parse, serialize } from '@wordpress/blocks';

const TRANSIENT_MEDIA_URL_PATTERN = /blob:[^"'\\\s<>]+/gi;

export function parseSerialized(value) {
    if (! value || typeof value !== 'string') {
        return [];
    }

    try {
        return parse(stripTransientMediaUrls(value));
    } catch (error) {
        console.warn('Unable to parse Gutenberg content.', error);

        return [];
    }
}

export function serializeBlocks(blocks) {
    try {
        return stripTransientMediaUrls(serialize(blocks || []));
    } catch (error) {
        console.warn('Unable to serialize Gutenberg blocks.', error);

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
    return createBlock('core/image', {
        url: asset.url,
        alt: asset.alt || '',
    });
}

export function createImageMedia(asset) {
    return {
        url: asset.url,
        source_url: asset.url,
        alt: asset.alt || '',
        title: asset.title || asset.filename || '',
        caption: asset.caption || '',
    };
}
