<?php

namespace Amazingbv\StatamicGutenberg;

use Amazingbv\StatamicGutenberg\BlockStyles\BlockStyleRepository;
use Amazingbv\StatamicGutenberg\Support\Duotone;

class ThemeJson
{
    private ?array $data = null;
    private bool $loaded = false;

    public function __construct(private BlockStyleRepository $blockStyles)
    {
        //
    }

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
        $data = $this->data();
        $css = $this->editorCss();
        $svgs = $this->svgDefinitions();

        if (! $data && $css === '' && $svgs === '') {
            return null;
        }

        return [
            'settings' => $this->settings() ?: new \stdClass,
            'css' => $css,
            'svgs' => $svgs,
        ];
    }

    public function publicAssetFile(string $path): ?string
    {
        $base = realpath(dirname($this->path()));

        if (! $base) {
            return null;
        }

        $relative = $this->themeFileRelativePath($path);

        if (! $relative) {
            return null;
        }

        $file = realpath($base.'/'.$relative);

        if (! $file || ! is_file($file) || ! str_starts_with($file, $base.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return in_array($file, $this->themeJsonReferencedFiles($base), true) ? $file : null;
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

    public function svgDefinitions(): string
    {
        $data = $this->data() ?? [];

        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];
        $styles = is_array($data['styles'] ?? null) ? $data['styles'] : [];
        $styles = $this->mergeBlockStyleVariations($styles);

        return trim(implode("\n", array_filter([
            Duotone::presetFilters($settings),
            $this->blockStyleDuotoneFilters($styles['blocks'] ?? [], $settings),
        ])));
    }

    public function editorCss(): string
    {
        return $this->cssForRoots([
            '.sgb-editor .sgb-page-frame',
            '.sgb-editor .sgb-canvas',
        ], [
            '.sgb-editor .sgb-page-frame',
        ]);
    }

    private function cssForRoots(array $roots, ?array $rootDeclarationRoots = null): string
    {
        $data = $this->data() ?? [];

        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];
        $styles = is_array($data['styles'] ?? null) ? $data['styles'] : [];
        $styles = $this->mergeBlockStyleVariations($styles);
        $rules = $this->fontFaceRules($settings);

        $rootDeclarations = array_merge(
            $this->presetVariables($settings),
            $this->customVariables($settings['custom'] ?? [], 'wp--custom'),
            $this->styleDeclarations($styles)
        );

        $rules[] = $this->rule($rootDeclarationRoots ?? $roots, $rootDeclarations);
        $rules = array_merge($rules, $this->presetUtilityRules($roots, $settings));
        $rules = array_merge($rules, $this->elementRules($roots, $styles['elements'] ?? []));
        $rules = array_merge($rules, $this->blockRules($roots, $styles['blocks'] ?? [], $settings));

        if (is_string($styles['css'] ?? null) && trim($styles['css']) !== '') {
            $rules[] = $this->scopeCustomCss($roots, $styles['css']);
        }

        return trim(implode("\n", array_filter($rules)));
    }

    private function mergeBlockStyleVariations(array $styles): array
    {
        $blocks = $this->blockStyles->themeJsonBlocks();

        if (! $blocks) {
            return $styles;
        }

        if (! is_array($styles['blocks'] ?? null)) {
            $styles['blocks'] = [];
        }

        foreach ($blocks as $blockName => $blockStyles) {
            if (! is_array($styles['blocks'][$blockName] ?? null)) {
                $styles['blocks'][$blockName] = [];
            }

            if (! is_array($styles['blocks'][$blockName]['variations'] ?? null)) {
                $styles['blocks'][$blockName]['variations'] = [];
            }

            $styles['blocks'][$blockName]['variations'] = array_merge(
                $styles['blocks'][$blockName]['variations'],
                $blockStyles['variations'] ?? [],
            );
        }

        return $styles;
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

        $variables = array_merge($variables, Duotone::presetVariables($settings));

        foreach ($this->presetList($settings['typography']['fontSizes'] ?? null) as $preset) {
            if ($declaration = $this->fontSizePresetVariable($preset)) {
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

        foreach ($this->presetList($settings['shadow']['presets'] ?? null) as $preset) {
            if ($declaration = $this->presetVariable('shadow', $preset, 'shadow')) {
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
            'ascentOverride' => 'ascent-override',
            'descentOverride' => 'descent-override',
            'fontDisplay' => 'font-display',
            'fontFamily' => 'font-family',
            'fontFeatureSettings' => 'font-feature-settings',
            'fontStyle' => 'font-style',
            'fontStretch' => 'font-stretch',
            'fontVariationSettings' => 'font-variation-settings',
            'fontWeight' => 'font-weight',
            'lineGapOverride' => 'line-gap-override',
            'sizeAdjust' => 'size-adjust',
        ];

        $declarations = $this->propertyDeclarations($fontFace, $map);
        $src = $this->fontFaceSource($fontFace['src'] ?? null);
        $unicodeRange = $this->unicodeRange($fontFace['unicodeRange'] ?? null);

        if ($src) {
            $declarations[] = 'src: '.$src;
        }

        if ($unicodeRange) {
            $declarations[] = 'unicode-range: '.$unicodeRange;
        }

        return $declarations;
    }

    private function unicodeRange(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $ranges = array_map('trim', explode(',', $value));

        if (! $ranges || in_array('', $ranges, true)) {
            return null;
        }

        foreach ($ranges as $range) {
            if (preg_match('/^U\+([0-9A-F]{1,6})(?:-([0-9A-F]{1,6}))?$/i', $range, $matches)) {
                $start = hexdec($matches[1]);
                $end = isset($matches[2]) ? hexdec($matches[2]) : $start;

                if ($start > 0x10FFFF || $end > 0x10FFFF || $start > $end) {
                    return null;
                }

                continue;
            }

            if (! preg_match('/^U\+([0-9A-F]{0,5}\\?{1,6})$/i', $range, $matches)) {
                return null;
            }

            $wildcard = $matches[1];

            if (strlen($wildcard) > 6 || hexdec(str_replace('?', 'F', $wildcard)) > 0x10FFFF) {
                return null;
            }
        }

        return implode(', ', $ranges);
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
        $url = $this->cssUrl($source, true);

        return $url ? 'url("'.$url.'")' : null;
    }

    private function cssUrl(mixed $source, bool $allowThemeFile = false): ?string
    {
        if (! is_string($source) && ! is_numeric($source)) {
            return null;
        }

        $source = trim((string) $source);

        if ($source === '' || preg_match('/(?:javascript:|vbscript:|data:|expression|[;"\'(){}<>\s])/i', $source)) {
            return null;
        }

        if ($allowThemeFile && str_starts_with($source, 'file:')) {
            $source = preg_replace('/^file:\.?\//', '', $source) ?? '';
            $source = ltrim(str_replace('\\', '/', $source), '/');

            if ($source === '' || str_contains($source, '..')) {
                return null;
            }

            return '/vendor/statamic-gutenberg/theme/'.str_replace('%2F', '/', rawurlencode($source));
        }

        if (preg_match('/^(https?:)?\/\//i', $source) || str_starts_with($source, '/')) {
            return $source;
        }

        return null;
    }

    private function themeJsonReferencedFiles(string $base): array
    {
        return collect($this->themeJsonFileReferences($this->data() ?? []))
            ->map(fn (string $source) => $this->themeFileFromReference($base, $source))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function themeJsonFileReferences(mixed $value): array
    {
        if (is_string($value) && str_starts_with(trim($value), 'file:')) {
            return [trim($value)];
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->flatMap(fn ($item) => $this->themeJsonFileReferences($item))
            ->values()
            ->all();
    }

    private function themeFileFromReference(string $base, string $source): ?string
    {
        $relative = preg_replace('/^file:/', '', $source) ?? '';
        $relative = preg_replace('/^\.\//', '', $relative) ?? '';
        $relative = $this->themeFileRelativePath($relative);

        if (! $relative) {
            return null;
        }

        $file = realpath($base.'/'.$relative);

        return $file && is_file($file) && str_starts_with($file, $base.DIRECTORY_SEPARATOR)
            ? $file
            : null;
    }

    private function themeFileRelativePath(string $path): ?string
    {
        $relative = ltrim(str_replace('\\', '/', rawurldecode($path)), '/');

        return $relative === '' || str_contains($relative, '..') ? null : $relative;
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

        foreach ($this->presetList($settings['shadow']['presets'] ?? null) as $preset) {
            $slug = $this->slug($preset['slug'] ?? null);

            if (! $slug) {
                continue;
            }

            $rules[] = $this->rule($this->descendantSelectors($roots, ".has-{$slug}-box-shadow"), [
                "box-shadow: var(--wp--preset--shadow--{$slug}) !important",
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

    private function blockRules(array $roots, mixed $blocks, array $settings): array
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

            $rules = array_merge($rules, $this->styleRules($this->descendantSelectors($roots, $selector), $styles, $settings, (string) $name));
        }

        return $rules;
    }

    private function styleRules(array $selectors, array $styles, array $settings = [], ?string $blockName = null, bool $important = false): array
    {
        $rules = [];
        $declarations = $this->styleDeclarations($styles);

        if ($declarations) {
            if ($important) {
                $declarations = $this->importantDeclarations($declarations);
            }

            $rules[] = $this->rule($selectors, $declarations);
        }

        foreach ($styles as $key => $value) {
            if (is_string($key) && str_starts_with($key, ':') && is_array($value)) {
                $rules = array_merge($rules, $this->styleRules(array_map(fn (string $selector) => $selector.$key, $selectors), $value, $settings, $blockName, $important));
            }
        }

        if ($blockName && is_array($styles['filter'] ?? null) && array_key_exists('duotone', $styles['filter'])) {
            $rules = array_merge($rules, $this->blockStyleDuotoneRules($selectors, $blockName, $styles['filter']['duotone'], $settings));
        }

        if (is_array($styles['variations'] ?? null)) {
            foreach ($styles['variations'] as $name => $variationStyles) {
                $slug = $this->slug((string) $name);

                if ($slug && is_array($variationStyles)) {
                    $rules = array_merge($rules, $this->styleRules($this->variationSelectors($selectors, $slug, $blockName), $variationStyles, $settings, $blockName, true));
                }
            }
        }

        if (is_array($styles['elements'] ?? null)) {
            foreach ($styles['elements'] as $name => $elementStyles) {
                $selector = $this->elementSelector((string) $name);

                if ($selector && is_array($elementStyles)) {
                    $rules = array_merge($rules, $this->styleRules($this->descendantSelectors($selectors, $selector), $elementStyles, $settings, $blockName, $important));
                }
            }
        }

        return $rules;
    }

    private function blockStyleDuotoneRules(array $selectors, string $blockName, mixed $value, array $settings): array
    {
        $relativeSelectors = $this->blockDuotoneRelativeSelectors($blockName);

        if (! $relativeSelectors) {
            return [];
        }

        $targetSelectors = collect($selectors)
            ->flatMap(fn (string $rootSelector) => collect($relativeSelectors)
                ->map(fn (string $part) => $rootSelector.' '.$part))
            ->implode(', ');
        $style = Duotone::styleForValue($value, $settings, $targetSelectors, 'theme-'.md5($blockName.serialize($value)));

        return $style ? [$style['css']] : [];
    }

    private function blockDuotoneRelativeSelectors(string $blockName): array
    {
        return match ($blockName) {
            'core/image' => ['img', '.components-placeholder'],
            'core/cover' => ['> .wp-block-cover__image-background', '> .wp-block-cover__video-background'],
            default => [],
        };
    }

    private function blockStyleDuotoneFilters(mixed $blocks, array $settings): string
    {
        if (! is_array($blocks)) {
            return '';
        }

        $filters = [];

        foreach ($blocks as $name => $styles) {
            if (! is_array($styles) || ! is_array($styles['filter'] ?? null) || ! array_key_exists('duotone', $styles['filter'])) {
                continue;
            }

            $selector = Duotone::blockSelector((string) $name);

            if (! $selector) {
                continue;
            }

            if (Duotone::presetSlug($styles['filter']['duotone'])) {
                continue;
            }

            $style = Duotone::styleForValue($styles['filter']['duotone'], $settings, $selector, 'theme-'.md5((string) $name.serialize($styles['filter']['duotone'])));

            if ($style && $style['svg']) {
                $filters[] = $style['svg'];
            }
        }

        return implode("\n", array_unique($filters));
    }

    private function styleDeclarations(array $styles): array
    {
        $declarations = [];

        if (is_array($styles['background'] ?? null)) {
            $declarations = array_merge($declarations, $this->backgroundDeclarations($styles['background']));
        }

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
                'textColumns' => 'column-count',
                'textDecoration' => 'text-decoration',
                'textIndent' => 'text-indent',
                'textTransform' => 'text-transform',
                'writingMode' => 'writing-mode',
            ]));
        }

        if (is_array($styles['spacing'] ?? null)) {
            $declarations = array_merge($declarations, $this->spacingDeclarations($styles['spacing']));
        }

        if (is_array($styles['border'] ?? null)) {
            $declarations = array_merge($declarations, $this->borderDeclarations($styles['border']));
        }

        if (is_array($styles['dimensions'] ?? null)) {
            $declarations = array_merge($declarations, $this->propertyDeclarations($styles['dimensions'], [
                'aspectRatio' => 'aspect-ratio',
                'height' => 'height',
                'minHeight' => 'min-height',
                'minWidth' => 'min-width',
                'width' => 'width',
            ]));
        }

        if (is_string($styles['shadow'] ?? null) || is_numeric($styles['shadow'] ?? null)) {
            if ($shadow = $this->safeCssValue($styles['shadow'])) {
                $declarations[] = 'box-shadow: '.$this->resolvePresetValue($shadow);
            }
        } elseif (is_array($styles['shadow'] ?? null)) {
            $declarations = array_merge($declarations, $this->propertyDeclarations($styles['shadow'], [
                'shadow' => 'box-shadow',
                'natural' => 'box-shadow',
            ]));
        }

        return $declarations;
    }

    private function borderDeclarations(array $border): array
    {
        $declarations = $this->propertyDeclarations($border, [
            'color' => 'border-color',
            'style' => 'border-style',
            'width' => 'border-width',
        ]);

        if (is_array($border['radius'] ?? null)) {
            $declarations = array_merge($declarations, $this->propertyDeclarations($border['radius'], [
                'topLeft' => 'border-top-left-radius',
                'top-left' => 'border-top-left-radius',
                'topRight' => 'border-top-right-radius',
                'top-right' => 'border-top-right-radius',
                'bottomLeft' => 'border-bottom-left-radius',
                'bottom-left' => 'border-bottom-left-radius',
                'bottomRight' => 'border-bottom-right-radius',
                'bottom-right' => 'border-bottom-right-radius',
            ]));
        } else {
            $declarations = array_merge($declarations, $this->propertyDeclarations($border, [
                'radius' => 'border-radius',
            ]));
        }

        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $value = $border[$side] ?? null;

            if (is_array($value)) {
                $declarations = array_merge($declarations, $this->propertyDeclarations($value, [
                    'color' => "border-{$side}-color",
                    'style' => "border-{$side}-style",
                    'width' => "border-{$side}-width",
                ]));
            } elseif ($safe = $this->safeCssValue($value)) {
                $declarations[] = "border-{$side}: ".$this->resolvePresetValue($safe);
            }
        }

        return $declarations;
    }

    private function backgroundDeclarations(array $background): array
    {
        $declarations = [];
        $image = $background['backgroundImage'] ?? null;
        $url = is_array($image) ? ($image['url'] ?? null) : $image;

        if ($url = $this->cssUrl($url, true)) {
            $declarations[] = 'background-image: url('.$url.')';
        }

        return array_merge($declarations, $this->propertyDeclarations($background, [
            'backgroundPosition' => 'background-position',
            'backgroundRepeat' => 'background-repeat',
            'backgroundSize' => 'background-size',
        ]));
    }

    private function importantDeclarations(array $declarations): array
    {
        return array_map(
            fn (string $declaration) => str_contains($declaration, '!important') ? $declaration : $declaration.' !important',
            $declarations
        );
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

    private function fontSizePresetVariable(array $preset): ?string
    {
        $slug = $this->slug($preset['slug'] ?? null);
        $value = $this->fontSizePresetValue($preset);

        return $slug && $value ? "--wp--preset--font-size--{$slug}: ".$value : null;
    }

    private function fontSizePresetValue(array $preset): ?string
    {
        $size = $this->safeCssValue($preset['size'] ?? null);
        $fluid = is_array($preset['fluid'] ?? null) ? $preset['fluid'] : null;
        $min = $this->safeCssValue($fluid['min'] ?? null);
        $max = $this->safeCssValue($fluid['max'] ?? null);

        if (! $min || ! $max) {
            return $size ? $this->resolvePresetValue($size) : null;
        }

        $minParsed = $this->numericCssSize($min);
        $maxParsed = $this->numericCssSize($max);

        if ($minParsed && $maxParsed && $minParsed['unit'] === $maxParsed['unit']) {
            $delta = round($maxParsed['value'] - $minParsed['value'], 6);

            return sprintf(
                'clamp(%s, calc(%s + %s%s * ((100vw - 320px) / 1280)), %s)',
                $this->resolvePresetValue($min),
                $this->resolvePresetValue($min),
                $delta,
                $minParsed['unit'],
                $this->resolvePresetValue($max)
            );
        }

        if ($size) {
            return sprintf('clamp(%s, %s, %s)', $this->resolvePresetValue($min), $this->resolvePresetValue($size), $this->resolvePresetValue($max));
        }

        return $this->resolvePresetValue($max);
    }

    private function numericCssSize(string $value): ?array
    {
        if (! preg_match('/^(-?\d+(?:\.\d+)?)([a-z%]+)$/i', trim($value), $matches)) {
            return null;
        }

        return [
            'value' => (float) $matches[1],
            'unit' => $matches[2],
        ];
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
        ];

        if (isset($special[$name])) {
            return $special[$name];
        }

        if (! str_contains($name, '/')) {
            return null;
        }

        return '.wp-block-'.$this->slug(str_replace('/', '-', preg_replace('/^core\//', '', $name)));
    }

    private function variationSelectors(array $selectors, string $slug, ?string $blockName): array
    {
        $selectors = array_map(fn (string $selector) => "{$selector}.is-style-{$slug}", $selectors);

        if ($blockName === 'core/button') {
            return $this->descendantSelectors($selectors, ':is(.wp-element-button, .wp-block-button__link)');
        }

        return $selectors;
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
        $css = trim($css);

        return $this->resolvePresetValue($this->prefixCustomCssSelectors($roots, $css));
    }

    private function prefixCustomCssSelectors(array $roots, string $css): string
    {
        $output = '';
        $offset = 0;

        while (($open = strpos($css, '{', $offset)) !== false) {
            $selector = substr($css, $offset, $open - $offset);
            $close = $this->matchingBraceOffset($css, $open);

            if ($close === null) {
                $output .= substr($css, $offset);
                $offset = strlen($css);
                break;
            }

            $inner = substr($css, $open + 1, $close - $open - 1);
            $trimmedSelector = trim($selector);

            if (str_starts_with($trimmedSelector, '@')) {
                $inner = $this->shouldScopeAtRule($trimmedSelector)
                    ? $this->prefixCustomCssSelectors($roots, $inner)
                    : $inner;
                $output .= $selector.'{'.$inner.'}';
            } else {
                $output .= $this->prefixSelectorList($roots, $selector).'{'.$inner.'}';
            }

            $offset = $close + 1;
        }

        return $output.substr($css, $offset);
    }

    private function shouldScopeAtRule(string $selector): bool
    {
        return ! preg_match('/^@(?:-webkit-|-moz-|-o-)?(?:keyframes|font-face|page|property|counter-style)\b/i', $selector);
    }

    private function matchingBraceOffset(string $css, int $open): ?int
    {
        $depth = 0;
        $quote = null;
        $escaped = false;
        $length = strlen($css);

        for ($index = $open; $index < $length; $index++) {
            $char = $css[$index];

            if ($quote !== null) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }

                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }

                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }

            if ($char === '{') {
                $depth++;
                continue;
            }

            if ($char === '}') {
                $depth--;

                if ($depth === 0) {
                    return $index;
                }
            }
        }

        return null;
    }

    private function prefixSelectorList(array $roots, string $selectorList): string
    {
        $leadingWhitespace = preg_match('/^\s*/', $selectorList, $leading) ? $leading[0] : '';
        $trailingWhitespace = preg_match('/\s*$/', $selectorList, $trailing) ? $trailing[0] : '';
        $prefixed = collect($this->splitSelectorList(trim($selectorList)))
            ->map(fn (string $selector) => trim($selector))
            ->filter()
            ->flatMap(function (string $selector) use ($roots) {
                return collect($roots)->map(function (string $root) use ($selector) {
                    return str_contains($selector, '&')
                        ? str_replace('&', $root, $selector)
                        : "{$root} {$selector}";
                });
            })
            ->implode(', ');

        return $prefixed === '' ? $selectorList : $leadingWhitespace.$prefixed.$trailingWhitespace;
    }

    private function splitSelectorList(string $selectorList): array
    {
        $selectors = [];
        $current = '';
        $depth = 0;
        $quote = null;
        $escaped = false;
        $length = strlen($selectorList);

        for ($index = 0; $index < $length; $index++) {
            $char = $selectorList[$index];

            if ($quote !== null) {
                $current .= $char;

                if ($escaped) {
                    $escaped = false;
                    continue;
                }

                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }

                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $current .= $char;
                continue;
            }

            if (in_array($char, ['(', '['], true)) {
                $depth++;
                $current .= $char;
                continue;
            }

            if (in_array($char, [')', ']'], true)) {
                $depth = max(0, $depth - 1);
                $current .= $char;
                continue;
            }

            if ($char === ',' && $depth === 0) {
                $selectors[] = $current;
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $selectors[] = $current;

        return $selectors;
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
