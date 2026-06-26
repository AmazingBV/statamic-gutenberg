<?php

use Amazingbv\StatamicGutenberg\Support\BlockWrapperContext;
use Amazingbv\StatamicGutenberg\Support\StatamicAssetImages;

if (! function_exists('get_block_wrapper_attributes')) {
    function get_block_wrapper_attributes(array $extra_attributes = []): string
    {
        return BlockWrapperContext::wrapperAttributes($extra_attributes);
    }
}

if (! function_exists('absint')) {
    function absint(mixed $value): int
    {
        return abs((int) $value);
    }
}

if (! function_exists('esc_attr')) {
    function esc_attr(mixed $value): string
    {
        return e((string) $value, false);
    }
}

if (! function_exists('esc_html')) {
    function esc_html(mixed $value): string
    {
        return e((string) $value, false);
    }
}

if (! function_exists('esc_html__')) {
    function esc_html__(string $text, ?string $domain = null): string
    {
        return esc_html(function_exists('__') ? __($text) : $text);
    }
}

if (! function_exists('esc_attr__')) {
    function esc_attr__(string $text, ?string $domain = null): string
    {
        return esc_attr(function_exists('__') ? __($text) : $text);
    }
}

if (! function_exists('esc_url')) {
    function esc_url(mixed $url): string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return '';
        }

        if (str_starts_with($url, '/') || preg_match('/^(https?:|mailto:|tel:)/i', $url)) {
            return e($url, false);
        }

        return '';
    }
}

if (! function_exists('sanitize_hex_color')) {
    function sanitize_hex_color(mixed $color): ?string
    {
        $color = trim((string) $color);

        return preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $color)
            ? $color
            : null;
    }
}

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field(mixed $value): string
    {
        $value = wp_strip_all_tags((string) $value);
        $value = preg_replace('/[\r\n\t ]+/', ' ', $value) ?? '';

        return trim($value);
    }
}

if (! function_exists('sanitize_title')) {
    function sanitize_title(mixed $title): string
    {
        $title = strtolower(trim((string) $title));
        $title = preg_replace('/[^a-z0-9_-]+/', '-', $title) ?? '';

        return trim($title, '-');
    }
}

if (! function_exists('wp_get_attachment_image')) {
    function wp_get_attachment_image(mixed $attachment_id, string|array $size = 'thumbnail', bool $icon = false, array $attr = []): string
    {
        return StatamicAssetImages::image($attachment_id, $size, $icon, $attr);
    }
}

if (! function_exists('wp_get_attachment_url')) {
    function wp_get_attachment_url(mixed $attachment_id): string
    {
        return StatamicAssetImages::url($attachment_id);
    }
}

if (! function_exists('wp_get_attachment_image_url')) {
    function wp_get_attachment_image_url(mixed $attachment_id, string|array $size = 'thumbnail', bool $icon = false): string
    {
        return StatamicAssetImages::url($attachment_id);
    }
}

if (! function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $value, int $flags = 0, int $depth = 512): string|false
    {
        return json_encode($value, $flags, $depth);
    }
}

if (! function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(mixed $text, bool $remove_breaks = false): string
    {
        $text = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text) ?? '';
        $text = strip_tags($text);

        if ($remove_breaks) {
            $text = preg_replace('/[\r\n\t ]+/', ' ', $text) ?? '';
        }

        return trim($text);
    }
}

if (! function_exists('wp_unique_id')) {
    function wp_unique_id(string $prefix = ''): string
    {
        static $counters = [];

        $counters[$prefix] = ($counters[$prefix] ?? 0) + 1;

        return $prefix.$counters[$prefix];
    }
}

if (! function_exists('_x')) {
    function _x(string $text, string $context, ?string $domain = null): string
    {
        return function_exists('__') ? __($text) : $text;
    }
}
