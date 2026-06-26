<?php

namespace Amazingbv\StatamicGutenberg\Support;

class Duotone
{
    public static function presetVariables(array $settings): array
    {
        return collect(self::presetList($settings['color']['duotone'] ?? null))
            ->map(function (array $preset) use ($settings) {
                $slug = self::slug($preset['slug'] ?? null);

                return $slug && self::colors($preset['colors'] ?? null, $settings)
                    ? "--wp--preset--duotone--{$slug}: url(#wp-duotone-{$slug})"
                    : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    public static function presetFilters(array $settings): string
    {
        $filters = collect(self::presetList($settings['color']['duotone'] ?? null))
            ->map(function (array $preset) use ($settings) {
                $slug = self::slug($preset['slug'] ?? null);
                $colors = self::colors($preset['colors'] ?? null, $settings);

                return $slug && $colors ? self::filter("wp-duotone-{$slug}", $colors) : null;
            })
            ->filter()
            ->values()
            ->all();

        return implode("\n", $filters);
    }

    public static function styleForValue(mixed $value, array $settings, string $selector, string $fallbackId): ?array
    {
        $fallbackId = self::slug($fallbackId) ?: 'custom';
        $presetSlug = self::presetSlug($value);
        $colors = self::colorsForValue($value, $settings);

        if ($colors === null) {
            return null;
        }

        if ($colors === 'unset') {
            return [
                'id' => null,
                'css' => self::rule($selector, ['filter: none']),
                'svg' => '',
            ];
        }

        $id = $presetSlug ? "wp-duotone-{$presetSlug}" : "wp-duotone-{$fallbackId}";

        return [
            'id' => $id,
            'css' => self::rule($selector, ["filter: url(#{$id})"]),
            'svg' => self::filter($id, $colors),
        ];
    }

    public static function blockSelector(string $blockName): ?string
    {
        return match ($blockName) {
            'core/image' => '.wp-block-image img, .wp-block-image .components-placeholder',
            'core/cover' => '.wp-block-cover > .wp-block-cover__image-background, .wp-block-cover > .wp-block-cover__video-background',
            default => null,
        };
    }

    public static function scopedSelector(string $className, string $selector): string
    {
        $className = self::slug($className);

        return collect(explode(',', $selector))
            ->map(fn (string $part) => trim($part))
            ->filter()
            ->map(fn (string $part) => str_starts_with($part, '.')
                ? ".{$className}{$part}"
                : ".{$className} {$part}")
            ->implode(', ');
    }

    public static function colorsForValue(mixed $value, array $settings): array|string|null
    {
        if (is_array($value)) {
            return self::colors($value, $settings);
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === 'unset') {
            return 'unset';
        }

        $slug = self::presetSlug($value);

        if (! $slug) {
            return null;
        }

        foreach (self::presetList($settings['color']['duotone'] ?? null) as $preset) {
            if (self::slug($preset['slug'] ?? null) === $slug) {
                return self::colors($preset['colors'] ?? null, $settings);
            }
        }

        return null;
    }

    public static function presetSlug(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if (preg_match('/^var:preset\|duotone\|([a-z0-9_-]+)$/i', $value, $matches)) {
            return self::slug($matches[1]);
        }

        if (preg_match('/^var\(--wp--preset--duotone--([a-z0-9_-]+)\)$/i', $value, $matches)) {
            return self::slug($matches[1]);
        }

        return null;
    }

    public static function filter(string $id, array $colors): string
    {
        $id = self::slug($id);
        $values = self::colorValues($colors);

        if (! $id || ! $values) {
            return '';
        }

        return '<svg xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 0 0" width="0" height="0" focusable="false" role="none" aria-hidden="true" style="visibility: hidden; position: absolute; left: -9999px; overflow: hidden;"><defs><filter id="'.
            e($id, false).
            '"><feColorMatrix color-interpolation-filters="sRGB" type="matrix" values=" .299 .587 .114 0 0 .299 .587 .114 0 0 .299 .587 .114 0 0 .299 .587 .114 0 0 "></feColorMatrix><feComponentTransfer color-interpolation-filters="sRGB"><feFuncR type="table" tableValues="'.
            e(implode(' ', $values['r']), false).
            '"></feFuncR><feFuncG type="table" tableValues="'.
            e(implode(' ', $values['g']), false).
            '"></feFuncG><feFuncB type="table" tableValues="'.
            e(implode(' ', $values['b']), false).
            '"></feFuncB><feFuncA type="table" tableValues="'.
            e(implode(' ', $values['a']), false).
            '"></feFuncA></feComponentTransfer><feComposite in2="SourceGraphic" operator="in"></feComposite></filter></defs></svg>';
    }

    private static function colors(mixed $colors, array $settings): ?array
    {
        if (! is_array($colors) || count($colors) < 2) {
            return null;
        }

        $colors = collect(array_slice($colors, 0, 2))
            ->map(fn ($color) => self::resolveColor($color, $settings))
            ->filter()
            ->values()
            ->all();

        return count($colors) === 2 ? $colors : null;
    }

    private static function resolveColor(mixed $color, array $settings): ?string
    {
        if (! is_string($color)) {
            return null;
        }

        $color = trim($color);

        if (preg_match('/^var:preset\|color\|([a-z0-9_-]+)$/i', $color, $matches)
            || preg_match('/^var\(--wp--preset--color--([a-z0-9_-]+)\)$/i', $color, $matches)) {
            $slug = self::slug($matches[1]);

            foreach (self::presetList($settings['color']['palette'] ?? null) as $preset) {
                if (self::slug($preset['slug'] ?? null) === $slug && is_string($preset['color'] ?? null)) {
                    return trim($preset['color']);
                }
            }

            return null;
        }

        return self::parseColor($color) ? $color : null;
    }

    private static function colorValues(array $colors): ?array
    {
        $values = ['r' => [], 'g' => [], 'b' => [], 'a' => []];

        foreach ($colors as $color) {
            $parsed = self::parseColor($color);

            if (! $parsed) {
                return null;
            }

            foreach ($values as $channel => $_) {
                $values[$channel][] = self::formatNumber($parsed[$channel]);
            }
        }

        return $values;
    }

    private static function parseColor(string $color): ?array
    {
        $color = trim($color);

        if (preg_match('/^#([a-f0-9]{3})$/i', $color, $matches)) {
            [$r, $g, $b] = str_split($matches[1]);

            return [
                'r' => hexdec($r.$r) / 255,
                'g' => hexdec($g.$g) / 255,
                'b' => hexdec($b.$b) / 255,
                'a' => 1,
            ];
        }

        if (preg_match('/^#([a-f0-9]{6})$/i', $color, $matches)) {
            return [
                'r' => hexdec(substr($matches[1], 0, 2)) / 255,
                'g' => hexdec(substr($matches[1], 2, 2)) / 255,
                'b' => hexdec(substr($matches[1], 4, 2)) / 255,
                'a' => 1,
            ];
        }

        if (preg_match('/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*(0|1|0?\.\d+))?\s*\)$/i', $color, $matches)) {
            $rgb = array_map('intval', array_slice($matches, 1, 3));

            if (max($rgb) > 255) {
                return null;
            }

            return [
                'r' => $rgb[0] / 255,
                'g' => $rgb[1] / 255,
                'b' => $rgb[2] / 255,
                'a' => isset($matches[4]) && $matches[4] !== '' ? (float) $matches[4] : 1,
            ];
        }

        return null;
    }

    private static function rule(string $selector, array $declarations): string
    {
        return $selector." {\n    ".implode(";\n    ", $declarations).";\n}";
    }

    private static function presetList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        if (array_is_list($value)) {
            return array_values(array_filter($value, 'is_array'));
        }

        return collect($value)
            ->filter(fn ($items) => is_array($items))
            ->flatMap(fn ($items) => self::presetList($items))
            ->values()
            ->all();
    }

    private static function slug(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $slug = strtolower(trim((string) $value));
        $slug = preg_replace('/[^a-z0-9_-]+/', '-', $slug);
        $slug = trim((string) $slug, '-');

        return $slug === '' ? null : $slug;
    }

    private static function formatNumber(float $value): string
    {
        $value = max(0, min(1, $value));

        return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.') ?: '0';
    }
}
