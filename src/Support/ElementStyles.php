<?php

namespace Amazingbv\StatamicGutenberg\Support;

use Amazingbv\StatamicGutenberg\Blocks\Block;

class ElementStyles
{
    public static function className(Block $block): ?string
    {
        $elements = self::elements($block);

        return self::rules($elements) ? 'wp-elements-'.substr(sha1($block->name().'|'.json_encode($elements)), 0, 10) : null;
    }

    public static function styleTag(Block $block): string
    {
        $className = self::className($block);

        if (! $className) {
            return '';
        }

        $rules = collect(self::rules(self::elements($block)))
            ->map(fn (array $rule) => sprintf(
                '.%s %s {%s}',
                $className,
                $rule['selector'],
                collect($rule['declarations'])
                    ->map(fn (string $declaration) => $declaration.';')
                    ->implode('')
            ))
            ->implode('');

        return $rules !== ''
            ? '<style data-statamic-gutenberg-element-styles>'.$rules.'</style>'
            : '';
    }

    private static function elements(Block $block): array
    {
        $style = $block->attribute('style', []);
        $elements = is_array($style) ? ($style['elements'] ?? []) : [];

        return is_array($elements) ? $elements : [];
    }

    private static function rules(array $elements): array
    {
        $rules = [];

        foreach ($elements as $name => $styles) {
            $selector = self::selector((string) $name);

            if (! $selector || ! is_array($styles)) {
                continue;
            }

            $declarations = self::styleDeclarations($styles);

            if ($declarations) {
                $rules[] = [
                    'selector' => $selector,
                    'declarations' => $declarations,
                ];
            }
        }

        return $rules;
    }

    private static function selector(string $name): ?string
    {
        return match ($name) {
            'button' => ':is(.wp-element-button, .wp-block-button__link, button:not(.components-button):not([class*="components-"]):not([class*="block-editor-"]))',
            'caption' => ':is(figcaption, .wp-element-caption)',
            'heading' => ':is(h1, h2, h3, h4, h5, h6, .wp-block-heading)',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => $name,
            'link' => 'a',
            default => null,
        };
    }

    private static function styleDeclarations(array $styles): array
    {
        return array_values(array_filter([
            ...self::colorDeclarations($styles['color'] ?? []),
            ...self::typographyDeclarations($styles['typography'] ?? []),
            ...self::borderDeclarations($styles['border'] ?? []),
            self::declaration('box-shadow', $styles['shadow'] ?? null),
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
            self::declaration('background', $color['gradient'] ?? null),
        ]));
    }

    private static function typographyDeclarations(mixed $typography): array
    {
        if (! is_array($typography)) {
            return [];
        }

        return array_values(array_filter([
            self::declaration('font-family', $typography['fontFamily'] ?? null),
            self::declaration('font-size', $typography['fontSize'] ?? null),
            self::declaration('font-style', $typography['fontStyle'] ?? null),
            self::declaration('font-weight', $typography['fontWeight'] ?? null),
            self::declaration('line-height', $typography['lineHeight'] ?? null),
            self::declaration('letter-spacing', $typography['letterSpacing'] ?? null),
            self::declaration('column-count', $typography['textColumns'] ?? null),
            self::declaration('text-decoration', $typography['textDecoration'] ?? null),
            self::declaration('text-indent', $typography['textIndent'] ?? null),
            self::declaration('text-transform', $typography['textTransform'] ?? null),
            self::declaration('writing-mode', $typography['writingMode'] ?? null),
        ]));
    }

    private static function borderDeclarations(mixed $border): array
    {
        if (! is_array($border)) {
            return [];
        }

        $declarations = [
            self::declaration('border-color', $border['color'] ?? null),
            self::declaration('border-style', $border['style'] ?? null),
            self::declaration('border-width', $border['width'] ?? null),
            ...self::borderRadiusDeclarations($border['radius'] ?? null),
        ];

        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $declarations = [
                ...$declarations,
                ...self::borderSideDeclarations($side, $border[$side] ?? null),
            ];
        }

        return array_values(array_filter($declarations));
    }

    private static function borderRadiusDeclarations(mixed $radius): array
    {
        if (! is_array($radius)) {
            return [self::declaration('border-radius', $radius)];
        }

        return [
            self::declaration('border-top-left-radius', $radius['topLeft'] ?? $radius['top-left'] ?? null),
            self::declaration('border-top-right-radius', $radius['topRight'] ?? $radius['top-right'] ?? null),
            self::declaration('border-bottom-left-radius', $radius['bottomLeft'] ?? $radius['bottom-left'] ?? null),
            self::declaration('border-bottom-right-radius', $radius['bottomRight'] ?? $radius['bottom-right'] ?? null),
        ];
    }

    private static function borderSideDeclarations(string $side, mixed $value): array
    {
        if (is_string($value) || is_numeric($value)) {
            return [self::declaration("border-{$side}", $value)];
        }

        if (! is_array($value)) {
            return [];
        }

        return [
            self::declaration("border-{$side}-color", $value['color'] ?? null),
            self::declaration("border-{$side}-style", $value['style'] ?? null),
            self::declaration("border-{$side}-width", $value['width'] ?? null),
        ];
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
}
