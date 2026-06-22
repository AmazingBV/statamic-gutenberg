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
    return createBlock('core/image', attributesForAssetBlock('core/image', asset));
}

export function createImageMedia(asset) {
    return createMediaPayload(asset);
}

export function createMediaPayload(asset = {}) {
    const mediaType = mediaTypeForAsset(asset);
    const url = asset.url || asset.source_url || '';
    const title = asset.title || asset.filename || '';
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

    return {
        url,
        source_url: url,
        link: asset.link || url,
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
    };
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
                src: url,
                caption: media.caption || '',
            };

        case 'core/cover':
            return {
                url,
                alt: media.alt || '',
                backgroundType: media.media_type === 'video' ? 'video' : 'image',
                useFeaturedImage: false,
            };

        case 'core/file':
            return {
                href: url,
                fileName: title,
                textLinkHref: url,
                downloadButtonText: 'Download',
            };

        case 'core/media-text':
            return {
                mediaUrl: url,
                mediaAlt: media.alt || '',
                mediaType: media.media_type === 'video' ? 'video' : 'image',
                useFeaturedImage: false,
            };

        case 'core/video':
            return {
                src: url,
                caption: media.caption || '',
            };

        case 'core/image':
        default:
            return {
                url,
                alt: media.alt || '',
                caption: media.caption || '',
            };
    }
}

export function mediaTypeForAsset(asset = {}) {
    return asset.media_type || asset.type || 'file';
}
