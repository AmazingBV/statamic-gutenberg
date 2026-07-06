<?php

namespace Amazingbv\StatamicGutenberg\Embeds;

class EmbedProviders
{
    private const SPOTIFY_TYPES = ['album', 'artist', 'episode', 'playlist', 'show', 'track'];

    public static function resolve(string $rawUrl): ?array
    {
        $rawUrl = trim($rawUrl);

        if ($rawUrl === '' || ! in_array(parse_url($rawUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return null;
        }

        return self::youtube($rawUrl)
            ?? self::vimeo($rawUrl)
            ?? self::spotify($rawUrl)
            ?? self::soundCloud($rawUrl);
    }

    private static function youtube(string $rawUrl): ?array
    {
        $host = self::host($rawUrl);
        $parts = self::pathParts($rawUrl);
        $query = self::query($rawUrl);
        $id = '';

        if ($host === 'youtu.be') {
            $id = $parts[0] ?? '';
        } elseif (in_array($host, ['youtube.com', 'youtube-nocookie.com'], true)) {
            if (($parts[0] ?? '') === 'embed') {
                $id = $parts[1] ?? '';
            } elseif ((parse_url($rawUrl, PHP_URL_PATH) ?: '') === '/watch') {
                $id = (string) ($query['v'] ?? '');
            } elseif (in_array($parts[0] ?? '', ['shorts', 'live'], true)) {
                $id = $parts[1] ?? '';
            }
        }

        if (! preg_match('/^[A-Za-z0-9_-]{6,}$/', $id)) {
            return null;
        }

        return [
            'slug' => 'youtube',
            'type' => 'video',
            'providerName' => 'YouTube',
            'providerUrl' => 'https://www.youtube.com/',
            'title' => 'YouTube video',
            'embedUrl' => 'https://www.youtube.com/embed/'.rawurlencode($id),
            'width' => '560',
            'height' => '315',
            'allow' => 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share',
            'allowFullscreen' => true,
        ];
    }

    private static function vimeo(string $rawUrl): ?array
    {
        if (! in_array(self::host($rawUrl), ['vimeo.com', 'player.vimeo.com'], true)) {
            return null;
        }

        $id = '';

        foreach (array_reverse(self::pathParts($rawUrl)) as $part) {
            if (preg_match('/^\d{6,}$/', $part)) {
                $id = $part;

                break;
            }
        }

        if (! $id) {
            return null;
        }

        return [
            'slug' => 'vimeo',
            'type' => 'video',
            'providerName' => 'Vimeo',
            'providerUrl' => 'https://vimeo.com/',
            'title' => 'Vimeo video',
            'embedUrl' => 'https://player.vimeo.com/video/'.rawurlencode($id),
            'width' => '640',
            'height' => '360',
            'allow' => 'autoplay; fullscreen; picture-in-picture',
            'allowFullscreen' => true,
        ];
    }

    private static function spotify(string $rawUrl): ?array
    {
        if (! in_array(self::host($rawUrl), ['open.spotify.com', 'play.spotify.com'], true)) {
            return null;
        }

        $parts = self::pathParts($rawUrl);

        if (str_starts_with($parts[0] ?? '', 'intl-')) {
            $parts = array_slice($parts, 1);
        }

        if (($parts[0] ?? '') === 'embed') {
            $parts = array_slice($parts, 1);
        }

        $type = $parts[0] ?? '';
        $id = $parts[1] ?? '';

        if (! in_array($type, self::SPOTIFY_TYPES, true) || ! preg_match('/^[A-Za-z0-9]{10,}$/', $id)) {
            return null;
        }

        return [
            'slug' => 'spotify',
            'type' => 'rich',
            'providerName' => 'Spotify',
            'providerUrl' => 'https://open.spotify.com/',
            'title' => 'Spotify embed',
            'embedUrl' => 'https://open.spotify.com/embed/'.$type.'/'.rawurlencode($id),
            'width' => '100%',
            'height' => '352',
            'allow' => 'autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture',
        ];
    }

    private static function soundCloud(string $rawUrl): ?array
    {
        $host = self::host($rawUrl);
        $sourceUrl = $rawUrl;

        if ($host === 'w.soundcloud.com' && str_starts_with(parse_url($rawUrl, PHP_URL_PATH) ?: '', '/player')) {
            $query = self::query($rawUrl);
            $sourceUrl = (string) ($query['url'] ?? '');
        } elseif (! in_array($host, ['soundcloud.com', 'on.soundcloud.com'], true)) {
            return null;
        }

        if (! in_array(parse_url($sourceUrl, PHP_URL_SCHEME), ['http', 'https'], true)
            || ! in_array(self::host($sourceUrl), ['soundcloud.com', 'on.soundcloud.com'], true)
        ) {
            return null;
        }

        return [
            'slug' => 'soundcloud',
            'type' => 'rich',
            'providerName' => 'SoundCloud',
            'providerUrl' => 'https://soundcloud.com/',
            'title' => 'SoundCloud embed',
            'embedUrl' => 'https://w.soundcloud.com/player/?url='.rawurlencode($sourceUrl).'&auto_play=false&hide_related=false&show_comments=true&show_user=true&show_reposts=false&show_teaser=true&visual=false',
            'width' => '100%',
            'height' => '166',
            'allow' => 'autoplay',
            'scrolling' => 'no',
        ];
    }

    private static function host(string $rawUrl): string
    {
        return strtolower((string) preg_replace('/^(www\.|m\.)/', '', (string) parse_url($rawUrl, PHP_URL_HOST)));
    }

    private static function pathParts(string $rawUrl): array
    {
        return array_values(array_filter(explode('/', parse_url($rawUrl, PHP_URL_PATH) ?: '')));
    }

    private static function query(string $rawUrl): array
    {
        $query = [];

        parse_str(parse_url($rawUrl, PHP_URL_QUERY) ?: '', $query);

        return $query;
    }
}
