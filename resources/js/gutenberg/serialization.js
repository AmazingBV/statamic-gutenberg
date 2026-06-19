import { createBlock, parse, serialize } from '@wordpress/blocks';

export function parseSerialized(value) {
    if (! value || typeof value !== 'string') {
        return [];
    }

    try {
        return parse(value);
    } catch (error) {
        console.warn('Unable to parse Gutenberg content.', error);

        return [];
    }
}

export function serializeBlocks(blocks) {
    try {
        return serialize(blocks || []);
    } catch (error) {
        console.warn('Unable to serialize Gutenberg blocks.', error);

        return '';
    }
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
        alt: asset.alt || '',
        title: asset.title || asset.filename || '',
    };
}
