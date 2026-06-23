<?php

namespace Amazingbv\StatamicGutenberg\Support;

use Amazingbv\StatamicGutenberg\Blocks\Block;

class BlockWrapperContext
{
    private static ?Block $block = null;

    public static function withBlock(Block $block, callable $callback): mixed
    {
        $previous = self::$block;
        self::$block = $block;

        try {
            return $callback();
        } finally {
            self::$block = $previous;
        }
    }

    public static function wrapperAttributes(array $extraAttributes = []): string
    {
        return self::renderAttributes(self::attributes(self::$block, $extraAttributes));
    }

    private static function attributes(?Block $block, array $extraAttributes): array
    {
        $attributes = $extraAttributes;
        $classes = [];

        if ($block) {
            $classes[] = self::baseClass($block->name());

            $align = $block->attribute('align');

            if (is_string($align) && preg_match('/^[a-z0-9_-]+$/i', $align)) {
                $classes[] = 'align'.$align;
            }

            $customClass = $block->attribute('className');

            if (is_string($customClass)) {
                $classes[] = $customClass;
            }

            $anchor = $block->attribute('anchor');

            if (! isset($attributes['id']) && is_string($anchor) && preg_match('/^[A-Za-z][A-Za-z0-9_:\-\.]*$/', $anchor)) {
                $attributes['id'] = $anchor;
            }
        }

        if (isset($attributes['class'])) {
            $classes[] = (string) $attributes['class'];
        }

        if ($classes) {
            $attributes['class'] = implode(' ', array_values(array_unique(array_filter(
                preg_split('/\s+/', implode(' ', $classes)) ?: []
            ))));
        }

        return $attributes;
    }

    private static function baseClass(string $name): string
    {
        if (str_starts_with($name, 'core/')) {
            $name = substr($name, 5);
        }

        return 'wp-block-'.str_replace(['/', '_'], '-', $name);
    }

    private static function renderAttributes(array $attributes): string
    {
        $html = '';

        foreach ($attributes as $key => $value) {
            if ($value === null || $value === false || $value === '') {
                continue;
            }

            $key = preg_replace('/[^a-zA-Z0-9_:\-]/', '', (string) $key);

            if ($key === '') {
                continue;
            }

            if ($value === true) {
                $html .= ' '.e($key);
                continue;
            }

            if (is_array($value) || is_object($value)) {
                continue;
            }

            $html .= sprintf(' %s="%s"', e($key), e((string) $value));
        }

        return $html;
    }
}
