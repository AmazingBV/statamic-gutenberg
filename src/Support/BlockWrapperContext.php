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

            self::appendPresetClasses($block, $classes);

            $styleDeclarations = self::styleDeclarations($block->attribute('style', []));

            if ($styleDeclarations) {
                $attributes['style'] = self::mergeStyles(
                    (string) ($attributes['style'] ?? ''),
                    implode('; ', $styleDeclarations)
                );
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

    private static function appendPresetClasses(Block $block, array &$classes): void
    {
        $textColor = self::safeSlug($block->attribute('textColor'));

        if ($textColor) {
            $classes[] = "has-{$textColor}-color";
            $classes[] = 'has-text-color';
        }

        $backgroundColor = self::safeSlug($block->attribute('backgroundColor'));

        if ($backgroundColor) {
            $classes[] = "has-{$backgroundColor}-background-color";
            $classes[] = 'has-background';
        }

        $fontSize = self::safeSlug($block->attribute('fontSize'));

        if ($fontSize) {
            $classes[] = "has-{$fontSize}-font-size";
        }

        $style = $block->attribute('style', []);
        $textAlign = is_array($style) ? self::safeSlug($style['typography']['textAlign'] ?? null) : null;

        if ($textAlign && in_array($textAlign, ['left', 'center', 'right', 'justify'], true)) {
            $classes[] = "has-text-align-{$textAlign}";
        }

        $shadow = is_array($style) ? self::presetSlug($style['shadow'] ?? null, 'shadow') : null;

        if ($shadow) {
            $classes[] = "has-{$shadow}-box-shadow";
        }
    }

    private static function styleDeclarations(mixed $style): array
    {
        if (! is_array($style)) {
            return [];
        }

        return array_values(array_filter([
            ...self::colorDeclarations($style['color'] ?? []),
            ...self::spacingDeclarations('margin', $style['spacing']['margin'] ?? []),
            ...self::spacingDeclarations('padding', $style['spacing']['padding'] ?? []),
            ...self::typographyDeclarations($style['typography'] ?? []),
            ...self::borderDeclarations($style['border'] ?? []),
            self::declaration('min-height', $style['dimensions']['minHeight'] ?? null),
            self::declaration('box-shadow', $style['shadow'] ?? null),
        ]));
    }

    private static function colorDeclarations(mixed $color): array
    {
        if (! is_array($color)) {
            return [];
        }

        return array_values(array_filter([
            self::declaration('color', $color['text'] ?? null),
            self::declaration('background-color', $color['background'] ?? null),
        ]));
    }

    private static function spacingDeclarations(string $property, mixed $values): array
    {
        if (is_string($values) || is_numeric($values)) {
            return array_filter([self::declaration($property, $values)]);
        }

        if (! is_array($values)) {
            return [];
        }

        $declarations = [];

        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $declarations[] = self::declaration("{$property}-{$side}", $values[$side] ?? null);
        }

        return array_values(array_filter($declarations));
    }

    private static function typographyDeclarations(mixed $typography): array
    {
        if (! is_array($typography)) {
            return [];
        }

        return array_values(array_filter([
            self::declaration('font-size', $typography['fontSize'] ?? null),
            self::declaration('line-height', $typography['lineHeight'] ?? null),
            self::declaration('letter-spacing', $typography['letterSpacing'] ?? null),
        ]));
    }

    private static function borderDeclarations(mixed $border): array
    {
        if (! is_array($border)) {
            return [];
        }

        return array_values(array_filter([
            self::declaration('border-color', $border['color'] ?? null),
            self::declaration('border-style', $border['style'] ?? null),
            self::declaration('border-width', $border['width'] ?? null),
            self::declaration('border-radius', $border['radius'] ?? null),
        ]));
    }

    private static function declaration(string $property, mixed $value): ?string
    {
        $value = self::safeCssValue($value);

        return $value ? "{$property}: {$value}" : null;
    }

    private static function safeCssValue(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = self::resolvePresetValue(trim((string) $value));

        if ($value === '' || preg_match('/[;{}<>]/', $value)) {
            return null;
        }

        if (preg_match('/(?:expression|javascript:|vbscript:|data:|url\s*\()/i', $value)) {
            return null;
        }

        return $value;
    }

    private static function resolvePresetValue(string $value): string
    {
        if (preg_match('/^var:preset\|([a-z0-9_-]+)\|([a-z0-9_-]+)$/i', $value, $matches)) {
            return sprintf('var(--wp--preset--%s--%s)', $matches[1], $matches[2]);
        }

        return $value;
    }

    private static function presetSlug(mixed $value, string $type): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        if (! preg_match('/^var:preset\|'.preg_quote($type, '/').'\|([a-z0-9_-]+)$/i', trim((string) $value), $matches)) {
            return null;
        }

        return self::safeSlug($matches[1]);
    }

    private static function safeSlug(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return preg_match('/^[a-z0-9_-]+$/i', $value) ? $value : null;
    }

    private static function mergeStyles(string $existing, string $addition): string
    {
        return implode('; ', array_filter([
            trim(rtrim($existing, ';')),
            trim(rtrim($addition, ';')),
        ]));
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
