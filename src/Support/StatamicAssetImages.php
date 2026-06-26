<?php

namespace Amazingbv\StatamicGutenberg\Support;

use Statamic\Facades\Asset;
use Throwable;

class StatamicAssetImages
{
    public static function url(mixed $assetId): string
    {
        $asset = self::asset($assetId);
        $url = $asset && method_exists($asset, 'url') ? $asset->url() : null;

        return is_string($url) ? $url : '';
    }

    public static function image(mixed $assetId, string|array $size = 'thumbnail', bool $icon = false, array $attributes = []): string
    {
        $asset = self::asset($assetId);

        if (! $asset || ! self::isImage($asset)) {
            return '';
        }

        $url = method_exists($asset, 'url') ? $asset->url() : null;

        if (! is_string($url) || trim($url) === '') {
            return '';
        }

        $attributes = [
            'src' => $url,
            ...$attributes,
        ];
        $attributes['src'] = $url;
        $attributes['alt'] = self::alt($asset, $attributes);

        foreach (['width', 'height'] as $dimension) {
            if (! array_key_exists($dimension, $attributes) && method_exists($asset, $dimension)) {
                try {
                    $value = $asset->{$dimension}();

                    if (is_numeric($value) && (int) $value > 0) {
                        $attributes[$dimension] = (string) (int) $value;
                    }
                } catch (Throwable) {
                    //
                }
            }
        }

        return '<img'.self::attributes($attributes).'>';
    }

    private static function asset(mixed $assetId): mixed
    {
        $assetId = self::normalizeReference($assetId);

        if ($assetId === '') {
            return null;
        }

        try {
            return Asset::find($assetId);
        } catch (Throwable) {
            return null;
        }
    }

    private static function normalizeReference(mixed $assetId): string
    {
        $assetId = trim((string) $assetId);

        if (str_starts_with($assetId, 'asset::')) {
            return substr($assetId, 7);
        }

        return $assetId;
    }

    private static function isImage(mixed $asset): bool
    {
        try {
            if (method_exists($asset, 'isImage') && $asset->isImage()) {
                return true;
            }

            return method_exists($asset, 'isSvg') && $asset->isSvg();
        } catch (Throwable) {
            return false;
        }
    }

    private static function alt(mixed $asset, array $attributes): string
    {
        $alt = $attributes['alt'] ?? null;

        if (is_scalar($alt) && trim((string) $alt) !== '') {
            return (string) $alt;
        }

        try {
            $assetAlt = method_exists($asset, 'get') ? $asset->get('alt', '') : '';

            return is_scalar($assetAlt) ? (string) $assetAlt : '';
        } catch (Throwable) {
            return '';
        }
    }

    private static function attributes(array $attributes): string
    {
        $html = '';

        foreach ($attributes as $name => $value) {
            if (! is_string($name) || ! preg_match('/^[a-zA-Z_:][a-zA-Z0-9_:.:-]*$/', $name)) {
                continue;
            }

            if ($value === null || $value === false) {
                continue;
            }

            if ($value === true) {
                $value = $name;
            }

            $html .= sprintf(' %s="%s"', $name, e((string) $value, false));
        }

        return $html;
    }
}
