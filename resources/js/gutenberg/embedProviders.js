export const SUPPORTED_EMBED_PROVIDER_SLUGS = ['youtube', 'vimeo', 'spotify', 'soundcloud'];

const SPOTIFY_TYPES = ['album', 'artist', 'episode', 'playlist', 'show', 'track'];

function parsedUrl(rawUrl) {
    if (! rawUrl) {
        return null;
    }

    try {
        return new URL(rawUrl);
    } catch {
        return null;
    }
}

function normalizedHost(url) {
    return url.hostname.toLowerCase().replace(/^(www\.|m\.)/, '');
}

function pathParts(url) {
    return url.pathname.split('/').filter(Boolean);
}

function attribute(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function iframeHtml(embed) {
    const attributes = {
        src: embed.embedUrl,
        title: embed.title,
        width: embed.width,
        height: embed.height,
        frameborder: '0',
        ...(embed.allow ? { allow: embed.allow } : {}),
        ...(embed.scrolling ? { scrolling: embed.scrolling } : {}),
    };

    const renderedAttributes = Object.entries(attributes)
        .filter(([, value]) => value !== undefined && value !== null && value !== '')
        .map(([name, value]) => `${name}="${attribute(value)}"`);

    if (embed.allowFullscreen) {
        renderedAttributes.push('allowfullscreen');
    }

    return `<iframe ${renderedAttributes.join(' ')}></iframe>`;
}

function youtubeEmbed(url) {
    const host = normalizedHost(url);
    const parts = pathParts(url);
    let id = '';

    if (host === 'youtu.be') {
        id = parts[0] || '';
    } else if (host === 'youtube.com' || host === 'youtube-nocookie.com') {
        if (parts[0] === 'embed') {
            id = parts[1] || '';
        } else if (url.pathname === '/watch') {
            id = url.searchParams.get('v') || '';
        } else if (parts[0] === 'shorts' || parts[0] === 'live') {
            id = parts[1] || '';
        }
    }

    if (! /^[A-Za-z0-9_-]{6,}$/.test(id)) {
        return null;
    }

    return {
        slug: 'youtube',
        type: 'video',
        providerName: 'YouTube',
        providerUrl: 'https://www.youtube.com/',
        title: 'YouTube video',
        embedUrl: `https://www.youtube.com/embed/${encodeURIComponent(id)}`,
        width: 560,
        height: 315,
        allow: 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share',
        allowFullscreen: true,
    };
}

function vimeoEmbed(url) {
    const host = normalizedHost(url);

    if (host !== 'vimeo.com' && host !== 'player.vimeo.com') {
        return null;
    }

    const id = [...pathParts(url)].reverse().find((part) => /^\d{6,}$/.test(part)) || '';

    if (! id) {
        return null;
    }

    return {
        slug: 'vimeo',
        type: 'video',
        providerName: 'Vimeo',
        providerUrl: 'https://vimeo.com/',
        title: 'Vimeo video',
        embedUrl: `https://player.vimeo.com/video/${encodeURIComponent(id)}`,
        width: 640,
        height: 360,
        allow: 'autoplay; fullscreen; picture-in-picture',
        allowFullscreen: true,
    };
}

function spotifyEmbed(url) {
    const host = normalizedHost(url);

    if (host !== 'open.spotify.com' && host !== 'play.spotify.com') {
        return null;
    }

    let parts = pathParts(url);

    if (parts[0]?.startsWith('intl-')) {
        parts = parts.slice(1);
    }

    if (parts[0] === 'embed') {
        parts = parts.slice(1);
    }

    const [type, id] = parts;

    if (! SPOTIFY_TYPES.includes(type) || ! /^[A-Za-z0-9]{10,}$/.test(id || '')) {
        return null;
    }

    return {
        slug: 'spotify',
        type: 'rich',
        providerName: 'Spotify',
        providerUrl: 'https://open.spotify.com/',
        title: 'Spotify embed',
        embedUrl: `https://open.spotify.com/embed/${type}/${encodeURIComponent(id)}`,
        width: '100%',
        height: 352,
        allow: 'autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture',
    };
}

function soundCloudEmbed(url) {
    const host = normalizedHost(url);
    let sourceUrl = url.toString();

    if (host === 'w.soundcloud.com' && url.pathname.startsWith('/player')) {
        sourceUrl = url.searchParams.get('url') || '';
    } else if (host !== 'soundcloud.com' && host !== 'on.soundcloud.com') {
        return null;
    }

    const source = parsedUrl(sourceUrl);

    if (! source || ! ['soundcloud.com', 'on.soundcloud.com'].includes(normalizedHost(source))) {
        return null;
    }

    return {
        slug: 'soundcloud',
        type: 'rich',
        providerName: 'SoundCloud',
        providerUrl: 'https://soundcloud.com/',
        title: 'SoundCloud embed',
        embedUrl: `https://w.soundcloud.com/player/?url=${encodeURIComponent(source.toString())}&auto_play=false&hide_related=false&show_comments=true&show_user=true&show_reposts=false&show_teaser=true&visual=false`,
        width: '100%',
        height: 166,
        allow: 'autoplay',
        scrolling: 'no',
    };
}

export function resolveEmbedProvider(rawUrl) {
    const url = parsedUrl(rawUrl);

    if (! url || ! ['http:', 'https:'].includes(url.protocol)) {
        return null;
    }

    return youtubeEmbed(url)
        || vimeoEmbed(url)
        || spotifyEmbed(url)
        || soundCloudEmbed(url);
}

export function oEmbedResponseForUrl(rawUrl) {
    const embed = resolveEmbedProvider(rawUrl);

    if (! embed) {
        return null;
    }

    return {
        type: embed.type,
        provider_name: embed.providerName,
        provider_url: embed.providerUrl,
        title: embed.title,
        html: iframeHtml(embed),
        width: embed.width,
        height: embed.height,
    };
}

export function unregisterUnsupportedEmbedVariations(blocksApi = {}) {
    const variations = typeof blocksApi.getBlockVariations === 'function'
        ? blocksApi.getBlockVariations('core/embed')
        : [];
    const unregister = blocksApi.unregisterBlockVariation;

    if (! Array.isArray(variations) || typeof unregister !== 'function') {
        return;
    }

    variations.forEach((variation) => {
        if (variation?.name && ! SUPPORTED_EMBED_PROVIDER_SLUGS.includes(variation.name)) {
            unregister('core/embed', variation.name);
        }
    });
}
