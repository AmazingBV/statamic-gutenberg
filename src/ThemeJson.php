<?php

namespace Amazingbv\StatamicGutenberg;

class ThemeJson
{
    private ?array $data = null;
    private bool $loaded = false;

    public function path(): string
    {
        return (string) config('statamic-gutenberg.theme_json_path', resource_path('vendor/statamic-gutenberg/theme.json'));
    }

    public function data(): ?array
    {
        if ($this->loaded) {
            return $this->data;
        }

        $this->loaded = true;
        $path = $this->path();

        if (! is_file($path)) {
            return $this->data = null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return $this->data = is_array($decoded) ? $decoded : null;
    }

    public function editorPayload(): ?array
    {
        if (! $this->data()) {
            return null;
        }

        return [
            'settings' => $this->settings(),
            'css' => $this->editorCss(),
        ];
    }

    public function settings(): array
    {
        $data = $this->data();

        return is_array($data['settings'] ?? null) ? $data['settings'] : [];
    }

    public function frontendCss(): string
    {
        return $this->cssForRoots(['.sgb-content']);
    }

    public function editorCss(): string
    {
        return $this->cssForRoots([
            '.sgb-editor .sgb-canvas',
        ]);
    }

    private function cssForRoots(array $roots): string
    {
        $data = $this->data();

        if (! $data) {
            return '';
        }

        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];
        $styles = is_array($data['styles'] ?? null) ? $data['styles'] : [];
        $rules = $this->fontFaceRules($settings);

        $rootDeclarations = array_merge(
            $this->presetVariables($settings),
            $this->customVariables($settings['custom'] ?? [], 'wp--custom'),
            $this->styleDeclarations($styles)
        );

        $rules[] = $this->rule($roots, $rootDeclarations);
        $rules = array_merge($rules, $this->presetUtilityRules($roots, $settings));
        $rules = array_merge($rules, $this->elementRules($roots, $styles['elements'] ?? []));
        $rules = array_merge($rules, $this->blockRules($roots, $styles['blocks'] ?? []));

        if (is_string($styles['css'] ?? null) && trim($styles['css']) !== '') {
            $rules[] = $this->scopeCustomCss($roots, $styles['css']);
        }

        return trim(implode("\n", array_filter($rules)));
    }

    private function presetVariables(array $settings): array
    {
        $variables = [];

        foreach ($this->presetList($settings['color']['palette'] ?? null) as $preset) {
            if ($declaration = $this->presetVariable('color', $preset, 'color')) {
                $variables[] = $declaration;
            }
        }

        foreach ($this->presetList($settings['color']['gradients'] ?? null) as $preset) {
            if ($declaration = $this->presetVariable('gradient', $preset, 'gradient')) {
                $variables[] = $declaration;
            }
        }

        foreach ($this->presetList($settings['typography']['fontSizes'] ?? null) as $preset) {
            if ($declaration = $this->presetVariable('font-size', $preset, 'size')) {
                $variables[] = $declaration;
            }
        }

        foreach ($this->presetList($settings['typography']['fontFamilies'] ?? null) as $preset) {
            if ($declaration = $this->presetVariable('font-family', $preset, 'fontFamily')) {
                $variables[] = $declaration;
            }
        }

        foreach ($this->presetList($settings['spacing']['spacingSizes'] ?? null) as $preset) {
            if ($declaration = $this->presetVariable('spacing', $preset, 'size')) {
                $variables[] = $declaration;
            }
        }

        if ($contentSize = $this->safeCssValue($settings['layout']['contentSize'] ?? null)) {
            $variables[] = '--wp--style--global--content-size: '.$contentSize;
        }

        if ($wideSize = $this->safeCssValue($settings['layout']['wideSize'] ?? null)) {
            $variables[] = '--wp--style--global--wide-size: '.$wideSize;
        }

        return $variables;
    }

    private function fontFaceRules(array $settings): array
    {
        $rules = [];

        foreach ($this->presetList($settings['typography']['fontFamilies'] ?? null) as $preset) {
            foreach ($this->presetList($preset['fontFace'] ?? null) as $fontFace) {
                $declarations = $this->fontFaceDeclarations($fontFace);

                if ($declarations) {
                    $rules[] = "@font-face {\n    ".implode(";\n    ", $declarations).";\n}";
                }
            }
        }

        return $rules;
    }

    private function fontFaceDeclarations(array $fontFace): array
    {
        $map = [
            'fontFamily' => 'font-family',
            'fontStyle' => 'font-style',
            'fontWeight' => 'font-weight',
            'fontDisplay' => 'font-display',
            'fontStretch' => 'font-stretch',
        ];

        $declarations = $this->propertyDeclarations($fontFace, $map);
        $src = $this->fontFaceSource($fontFace['src'] ?? null);

        if ($src) {
            $declarations[] = 'src: '.$src;
        }

        return $declarations;
    }

    private function fontFaceSource(mixed $value): ?string
    {
        $sources = is_array($value) ? $value : [$value];
        $sources = collect($sources)
            ->map(fn ($source) => $this->fontFaceUrl($source))
            ->filter()
            ->values()
            ->all();

        return $sources ? implode(', ', $sources) : null;
    }

    private function fontFaceUrl(mixed $source): ?string
    {
        if (! is_string($source)) {
            return null;
        }

        $source = trim($source);

        if ($source === '' || preg_match('/(?:javascript|expression|<|>|\{|\})/i', $source)) {
            return null;
        }

        if (str_starts_with($source, 'file:')) {
            $source = preg_replace('/^file:\.?\//', '', $source) ?? '';
            $source = ltrim(str_replace('\\', '/', $source), '/');

            if ($source === '' || str_contains($source, '..')) {
                return null;
            }

            return 'url("/vendor/statamic-gutenberg/theme/'.str_replace('%2F', '/', rawurlencode($source)).'")';
        }

        if (preg_match('/^(https?:)?\/\//i', $source) || str_starts_with($source, '/')) {
            return 'url("'.str_replace('"', '%22', $source).'")';
        }

        return null;
    }

    private function presetUtilityRules(array $roots, array $settings): array
    {
        $rules = [];

        foreach ($this->presetList($settings['color']['palette'] ?? null) as $preset) {
            $slug = $this->slug($preset['slug'] ?? null);

            if (! $slug) {
                continue;
            }

            $rules[] = $this->rule($this->descendantSelectors($roots, ".has-{$slug}-color"), [
                "color: var(--wp--preset--color--{$slug}) !important",
            ]);
            $rules[] = $this->rule($this->descendantSelectors($roots, ".has-{$slug}-background-color"), [
                "background-color: var(--wp--preset--color--{$slug}) !important",
            ]);
            $rules[] = $this->rule($this->descendantSelectors($roots, ".has-{$slug}-border-color"), [
                "border-color: var(--wp--preset--color--{$slug}) !important",
            ]);
        }

        foreach ($this->presetList($settings['color']['gradients'] ?? null) as $preset) {
            $slug = $this->slug($preset['slug'] ?? null);

            if (! $slug) {
                continue;
            }

            $rules[] = $this->rule($this->descendantSelectors($roots, ".has-{$slug}-gradient-background"), [
                "background: var(--wp--preset--gradient--{$slug}) !important",
            ]);
        }

        foreach ($this->presetList($settings['typography']['fontSizes'] ?? null) as $preset) {
            $slug = $this->slug($preset['slug'] ?? null);

            if (! $slug) {
                continue;
            }

            $rules[] = $this->rule($this->descendantSelectors($roots, ".has-{$slug}-font-size"), [
                "font-size: var(--wp--preset--font-size--{$slug}) !important",
            ]);
        }

        foreach ($this->presetList($settings['typography']['fontFamilies'] ?? null) as $preset) {
            $slug = $this->slug($preset['slug'] ?? null);

            if (! $slug) {
                continue;
            }

            $rules[] = $this->rule($this->descendantSelectors($roots, ".has-{$slug}-font-family"), [
                "font-family: var(--wp--preset--font-family--{$slug}) !important",
            ]);
        }

        return $rules;
    }

    private function elementRules(array $roots, mixed $elements): array
    {
        if (! is_array($elements)) {
            return [];
        }

        $rules = [];

        foreach ($elements as $name => $styles) {
            $selector = $this->elementSelector((string) $name);

            if (! $selector || ! is_array($styles)) {
                continue;
            }

            $rules = array_merge($rules, $this->styleRules($this->descendantSelectors($roots, $selector), $styles));
        }

        return $rules;
    }

    private function blockRules(array $roots, mixed $blocks): array
    {
        if (! is_array($blocks)) {
            return [];
        }

        $rules = [];

        foreach ($blocks as $name => $styles) {
            $selector = $this->blockSelector((string) $name);

            if (! $selector || ! is_array($styles)) {
                continue;
            }

            $rules = array_merge($rules, $this->styleRules($this->descendantSelectors($roots, $selector), $styles));
        }

        return $rules;
    }

    private function styleRules(array $selectors, array $styles): array
    {
        $rules = [];
        $declarations = $this->styleDeclarations($styles);

        if ($declarations) {
            $rules[] = $this->rule($selectors, $declarations);
        }

        foreach ($styles as $key => $value) {
            if (is_string($key) && str_starts_with($key, ':') && is_array($value)) {
                $rules = array_merge($rules, $this->styleRules(array_map(fn (string $selector) => $selector.$key, $selectors), $value));
            }
        }

        if (is_array($styles['elements'] ?? null)) {
            foreach ($styles['elements'] as $name => $elementStyles) {
                $selector = $this->elementSelector((string) $name);

                if ($selector && is_array($elementStyles)) {
                    $rules = array_merge($rules, $this->styleRules($this->descendantSelectors($selectors, $selector), $elementStyles));
                }
            }
        }

        return $rules;
    }

    private function styleDeclarations(array $styles): array
    {
        $declarations = [];

        if (is_array($styles['color'] ?? null)) {
            $declarations = array_merge($declarations, $this->colorDeclarations($styles['color']));
        }

        if (is_array($styles['typography'] ?? null)) {
            $declarations = array_merge($declarations, $this->propertyDeclarations($styles['typography'], [
                'fontFamily' => 'font-family',
                'fontSize' => 'font-size',
                'fontStyle' => 'font-style',
                'fontWeight' => 'font-weight',
                'letterSpacing' => 'letter-spacing',
                'lineHeight' => 'line-height',
                'textDecoration' => 'text-decoration',
                'textTransform' => 'text-transform',
                'writingMode' => 'writing-mode',
            ]));
        }

        if (is_array($styles['spacing'] ?? null)) {
            $declarations = array_merge($declarations, $this->spacingDeclarations($styles['spacing']));
        }

        if (is_array($styles['border'] ?? null)) {
            $declarations = array_merge($declarations, $this->propertyDeclarations($styles['border'], [
                'color' => 'border-color',
                'radius' => 'border-radius',
                'style' => 'border-style',
                'width' => 'border-width',
            ]));
        }

        if (is_array($styles['dimensions'] ?? null)) {
            $declarations = array_merge($declarations, $this->propertyDeclarations($styles['dimensions'], [
                'minHeight' => 'min-height',
            ]));
        }

        if (is_array($styles['shadow'] ?? null)) {
            $declarations = array_merge($declarations, $this->propertyDeclarations($styles['shadow'], [
                'natural' => 'box-shadow',
            ]));
        }

        return $declarations;
    }

    private function colorDeclarations(array $color): array
    {
        $declarations = $this->propertyDeclarations($color, [
            'text' => 'color',
            'background' => 'background-color',
            'gradient' => 'background',
        ]);

        if ($link = $this->safeCssValue($color['link'] ?? null)) {
            $declarations[] = '--wp--style--color--link: '.$this->resolvePresetValue($link);
        }

        return $declarations;
    }

    private function spacingDeclarations(array $spacing): array
    {
        $declarations = [];

        foreach (['margin', 'padding'] as $property) {
            $value = $spacing[$property] ?? null;

            if (is_array($value)) {
                foreach (['top', 'right', 'bottom', 'left'] as $side) {
                    if ($safe = $this->safeCssValue($value[$side] ?? null)) {
                        $declarations[] = "{$property}-{$side}: ".$this->resolvePresetValue($safe);
                    }
                }
            } elseif ($safe = $this->safeCssValue($value)) {
                $declarations[] = "{$property}: ".$this->resolvePresetValue($safe);
            }
        }

        if ($blockGap = $this->safeCssValue($spacing['blockGap'] ?? null)) {
            $blockGap = $this->resolvePresetValue($blockGap);
            $declarations[] = '--wp--style--block-gap: '.$blockGap;
            $declarations[] = 'gap: '.$blockGap;
        }

        return $declarations;
    }

    private function propertyDeclarations(array $values, array $map): array
    {
        $declarations = [];

        foreach ($map as $key => $property) {
            if ($safe = $this->safeCssValue($values[$key] ?? null)) {
                $declarations[] = "{$property}: ".$this->resolvePresetValue($safe);
            }
        }

        return $declarations;
    }

    private function presetList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        if (array_is_list($value)) {
            return array_values(array_filter($value, 'is_array'));
        }

        return collect($value)
            ->filter(fn ($items) => is_array($items))
            ->flatMap(fn ($items) => $this->presetList($items))
            ->values()
            ->all();
    }

    private function presetVariable(string $type, array $preset, string $valueKey): ?string
    {
        $slug = $this->slug($preset['slug'] ?? null);
        $value = $this->safeCssValue($preset[$valueKey] ?? null);

        return $slug && $value ? "--wp--preset--{$type}--{$slug}: ".$this->resolvePresetValue($value) : null;
    }

    private function customVariables(mixed $value, string $prefix): array
    {
        if (! is_array($value)) {
            return [];
        }

        $variables = [];

        foreach ($value as $key => $item) {
            $slug = $this->slug((string) $key);

            if (! $slug) {
                continue;
            }

            if (is_array($item)) {
                $variables = array_merge($variables, $this->customVariables($item, "{$prefix}--{$slug}"));
            } elseif ($safe = $this->safeCssValue($item)) {
                $variables[] = "--{$prefix}--{$slug}: ".$this->resolvePresetValue($safe);
            }
        }

        return $variables;
    }

    private function elementSelector(string $name): ?string
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

    private function blockSelector(string $name): ?string
    {
        $special = [
            'core/paragraph' => 'p',
            'core/heading' => '.wp-block-heading',
            'statamic/hero' => ':is(.wp-block-statamic-hero, .sgb-custom-block--hero)',
            'statamic/cta' => ':is(.wp-block-statamic-cta, .sgb-custom-block--cta)',
        ];

        if (isset($special[$name])) {
            return $special[$name];
        }

        if (! str_contains($name, '/')) {
            return null;
        }

        return '.wp-block-'.$this->slug(str_replace('/', '-', preg_replace('/^core\//', '', $name)));
    }

    private function rule(array $selectors, array $declarations): string
    {
        $selectors = array_values(array_filter(array_unique($selectors)));
        $declarations = array_values(array_filter(array_unique($declarations)));

        if (! $selectors || ! $declarations) {
            return '';
        }

        return implode(', ', $selectors)." {\n    ".implode(";\n    ", $declarations).";\n}";
    }

    private function descendantSelectors(array $roots, string $selector): array
    {
        return array_map(fn (string $root) => "{$root} {$selector}", $roots);
    }

    private function scopeCustomCss(array $roots, string $css): string
    {
        $root = implode(', ', $roots);

        return $this->resolvePresetValue(str_replace('&', $root, trim($css)));
    }

    private function resolvePresetValue(string $value): string
    {
        return preg_replace_callback('/var:preset\|([a-z0-9_-]+)\|([a-z0-9_-]+)/i', function (array $matches) {
            return sprintf('var(--wp--preset--%s--%s)', $this->slug($matches[1]), $this->slug($matches[2]));
        }, $value) ?? $value;
    }

    private function safeCssValue(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || preg_match('/(?:javascript|expression|<|>|\{|\})/i', $value)) {
            return null;
        }

        return str_replace(';', '', $value);
    }

    private function slug(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $slug = strtolower(trim((string) $value));
        $slug = preg_replace('/[^a-z0-9_-]+/', '-', $slug);
        $slug = trim((string) $slug, '-');

        return $slug !== '' ? $slug : null;
    }
}
