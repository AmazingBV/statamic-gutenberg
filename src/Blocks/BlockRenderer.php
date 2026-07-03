<?php

namespace Amazingbv\StatamicGutenberg\Blocks;

use Amazingbv\StatamicGutenberg\Bard\BardBlockRepository;
use Amazingbv\StatamicGutenberg\Icons\IconRepository;
use Amazingbv\StatamicGutenberg\Patterns\PatternRepository;
use Amazingbv\StatamicGutenberg\Support\Duotone;
use Amazingbv\StatamicGutenberg\Support\BlockWrapperContext;
use Amazingbv\StatamicGutenberg\Support\ElementStyles;
use Amazingbv\StatamicGutenberg\Support\StatamicAssetImages;
use Amazingbv\StatamicGutenberg\Support\WpBlock;
use Amazingbv\StatamicGutenberg\ThemeJson;
use DOMDocument;
use DOMElement;
use Illuminate\Support\HtmlString;
use Stringable;
use Statamic\Facades\Entry;
use Throwable;

class BlockRenderer
{
    private const STATIC_WRAPPER_SUPPORT_TAGS = [
        'core/audio' => ['figure', 'audio'],
        'core/button' => ['div'],
        'core/code' => ['pre'],
        'core/details' => ['details'],
        'core/embed' => ['figure'],
        'core/file' => ['div'],
        'core/heading' => ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'],
        'core/list' => ['ul', 'ol'],
        'core/list-item' => ['li'],
        'core/math' => ['div'],
        'core/media-text' => ['div'],
        'core/paragraph' => ['p'],
        'core/preformatted' => ['pre'],
        'core/pullquote' => ['figure'],
        'core/quote' => ['blockquote'],
        'core/separator' => ['hr', 'div'],
        'core/spacer' => ['div'],
        'core/table' => ['figure'],
        'core/verse' => ['pre'],
        'core/video' => ['figure', 'video'],
    ];

    private const BASE_CLASSLESS_STATIC_BLOCKS = [
        'core/paragraph',
    ];

    public function __construct(
        private BlockParser $parser,
        private BlockRegistry $registry,
        private Sanitizer $sanitizer,
        private PatternRepository $patterns,
        private ThemeJson $themeJson,
        private BardBlockRepository $bardBlocks,
    ) {
        //
    }

    public function render(?string $content, array $options = []): HtmlString
    {
        $content ??= '';
        $options = array_merge(config('statamic-gutenberg', []), $options);

        if (($options['render_mode'] ?? 'blade') === 'raw') {
            $html = ($options['sanitize_html'] ?? true)
                ? $this->sanitizer->sanitize($content)
                : $content;

            return new HtmlString($html);
        }

        return new HtmlString($this->renderBlocks($this->parser->parse($content), $options));
    }

    public function renderBlocks(array $blocks, array $options = []): string
    {
        return collect($blocks)
            ->map(fn (Block $block) => $this->renderBlock($block, $options))
            ->implode('');
    }

    public function renderBlock(Block $block, array $options = []): string
    {
        if ($block->isFreeform()) {
            return $this->sanitize($block->renderableHtml(), $options);
        }

        $allowed = $options['allowed_blocks'] ?? null;

        if ($block->name() !== 'core/block' && ! $this->registry->isAllowed($block->name(), is_array($allowed) ? $allowed : null)) {
            if ($options['allow_unknown_blocks'] ?? false) {
                return $this->sanitize($block->renderableHtml(), $options);
            }

            return '';
        }

        $definition = $this->registry->definition($block->name());
        $inner = $this->renderBlocks($block->innerBlocks(), $options);

        if (is_callable($definition)) {
            $html = (string) $definition($block, $inner, $this);
        } elseif (is_array($definition) && isset($definition['view'])) {
            $html = (string) BlockWrapperContext::withBlock($block, fn (): string => view($definition['view'], [
                'block' => $block,
                'attrs' => $block->attributes(),
                'inner' => new HtmlString($inner),
            ])->render());
        } elseif (is_array($definition) && isset($definition['bard_block'])) {
            $html = $this->renderBardBlock($block, $definition['bard_block'], $options);
        } elseif (is_array($definition) && isset($definition['custom_block'])) {
            $html = $this->renderCustomBlock($block, $inner, $definition['custom_block'], $options);
        } else {
            $html = $this->renderCoreBlock($block, $inner, $options);
        }

        return $this->applyElementStyles($block, $html);
    }

    private function applyElementStyles(Block $block, string $html): string
    {
        if ($html === '') {
            return '';
        }

        return ElementStyles::styleTag($block).$html;
    }

    private function renderBardBlock(Block $block, array $definition, array $options): string
    {
        return $this->sanitize($this->bardBlocks->render($definition['name'] ?? $block->name(), $block->attributes()), $options);
    }

    private function renderCustomBlock(Block $block, string $inner, array $definition, array $options): string
    {
        $render = $definition['render'] ?? null;

        if (is_string($render) && is_file($render)) {
            $attributes = $block->attributes();
            $content = $inner;
            $metadata = is_array($definition['metadata'] ?? null) ? $definition['metadata'] : [];
            $renderer = $this;
            $wpBlock = new WpBlock($block, $this, $options);
            $render_blocks = fn (array $blocks): string => $this->renderBlocks($blocks, $options);

            return (string) BlockWrapperContext::withBlock($block, function () use (
                $render,
                $attributes,
                $content,
                $metadata,
                $wpBlock,
                $renderer,
                $render_blocks
            ): string {
                $block = $wpBlock;

                ob_start();
                $source = $this->rewriteWordPressTranslationCalls((string) file_get_contents($render));
                $result = $source === null ? include $render : eval('?>'.$source);
                $output = (string) ob_get_clean();

                if (is_string($result) || $result instanceof Stringable) {
                    return (string) $result;
                }

                return $output;
            });
        }

        return trim($this->sanitize($block->renderableHtml(), $options));
    }

    private function rewriteWordPressTranslationCalls(string|false $source): ?string
    {
        if (! is_string($source) || ! str_contains($source, '__')) {
            return null;
        }

        $tokens = token_get_all($source);
        $rewritten = '';
        $changed = false;

        foreach ($tokens as $index => $token) {
            if (
                is_array($token)
                && $token[0] === T_STRING
                && $token[1] === '__'
                && $this->isFunctionCallToken($tokens, $index)
            ) {
                $rewritten .= 'sgb_wp_translate';
                $changed = true;

                continue;
            }

            $rewritten .= is_array($token) ? $token[1] : $token;
        }

        return $changed ? $rewritten : null;
    }

    private function isFunctionCallToken(array $tokens, int $index): bool
    {
        $next = $this->nextMeaningfulToken($tokens, $index);

        if ($next !== '(') {
            return false;
        }

        $previous = $this->previousMeaningfulToken($tokens, $index);

        if (is_array($previous)) {
            return ! in_array($previous[0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true);
        }

        return $previous !== '\\';
    }

    private function nextMeaningfulToken(array $tokens, int $index): mixed
    {
        for ($i = $index + 1; $i < count($tokens); $i++) {
            $token = $tokens[$i];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $token;
        }

        return null;
    }

    private function previousMeaningfulToken(array $tokens, int $index): mixed
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            $token = $tokens[$i];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $token;
        }

        return null;
    }

    private function renderCoreBlock(Block $block, string $inner, array $options): string
    {
        return match ($block->name()) {
            'core/block' => $this->renderReusableBlock($block, $options),
            'core/group' => $this->renderWrapperBlock($block, $inner, $options, 'div', 'wp-block-group', ['div', 'section', 'main', 'article', 'aside', 'header', 'footer']),
            'core/columns' => $this->renderWrapperBlock($block, $inner, $options, 'div', 'wp-block-columns'),
            'core/column' => $this->renderWrapperBlock($block, $inner, $options, 'div', 'wp-block-column'),
            'core/buttons' => $this->renderWrapperBlock($block, $inner, $options, 'div', 'wp-block-buttons'),
            'core/form' => $this->renderWrapperBlock($block, $inner, $options, 'form', 'wp-block-form', ['form']),
            'core/playlist' => $this->renderWrapperBlock($block, $inner, $options, 'figure', 'wp-block-playlist', ['figure']),
            'core/query' => $this->renderWrapperBlock($block, $inner, $options, $this->safeTagName((string) $block->attribute('tagName', 'div'), 'div', ['div', 'main', 'section', 'article']), 'wp-block-query', ['div', 'main', 'section', 'article']),
            'core/terms-query' => $this->renderWrapperBlock($block, $inner, $options, $this->safeTagName((string) $block->attribute('tagName', 'div'), 'div', ['div', 'main', 'section', 'article']), 'wp-block-terms-query', ['div', 'main', 'section', 'article']),
            'core/post-template' => $this->renderWrapperBlock($block, $inner, $options, 'ul', 'wp-block-post-template', ['ul', 'ol', 'div']),
            'core/term-template' => $this->renderWrapperBlock($block, $inner, $options, 'ul', 'wp-block-term-template', ['ul', 'ol', 'div']),
            'core/query-pagination' => $this->renderWrapperBlock($block, $inner, $options, 'nav', 'wp-block-query-pagination', ['nav', 'div']),
            'core/comments' => $this->renderWrapperBlock($block, $inner, $options, $this->safeTagName((string) $block->attribute('tagName', 'div'), 'div', ['div', 'section']), 'wp-block-comments', ['div', 'section']),
            'core/comment-template' => $this->renderWrapperBlock($block, $inner, $options, 'ol', 'wp-block-comment-template', ['ol', 'ul', 'div']),
            'core/comments-pagination' => $this->renderWrapperBlock($block, $inner, $options, 'nav', 'wp-block-comments-pagination', ['nav', 'div']),
            'core/accordion' => $this->renderWrapperBlock($block, $inner, $options, 'div', 'wp-block-accordion', ['div'], [
                'data-sgb-accordion-autoclose' => $this->truthy($block->attribute('autoclose', false)) ? 'true' : 'false',
            ]),
            'core/tabs' => $this->renderWrapperBlock($block, $inner, $options, 'div', 'wp-block-tabs', ['div'], [
                'data-sgb-active-tab-index' => (string) max(0, (int) $block->attribute('activeTabIndex', 0)),
            ]),
            'core/tab-list' => $this->renderWrapperBlock($block, $inner, $options, 'div', 'wp-block-tab-list', ['div'], [
                'role' => 'tablist',
            ]),
            'core/tab-panels' => $this->renderWrapperBlock($block, $inner, $options, 'div', 'wp-block-tab-panels'),
            'core/tab' => $this->renderWrapperBlock($block, $inner, $options, 'button', 'wp-block-tab', ['button'], [
                'type' => 'button',
                'role' => 'tab',
            ]),
            'core/tab-panel' => $this->renderWrapperBlock($block, $inner, $options, 'section', 'wp-block-tab-panel', ['section'], [
                'role' => 'tabpanel',
                'data-sgb-tab-label' => (string) $block->attribute('label', ''),
            ]),
            'core/audio' => $this->renderAudio($block, $options),
            'core/separator' => $this->renderSeparator($block, $options),
            'core/spacer' => $this->renderSpacer($block, $options),
            'core/more' => $this->renderMoreMarker($block, $options),
            'core/nextpage' => $this->renderNextPageMarker($block, $options),
            'core/embed' => $this->renderEmbed($block, $options),
            'core/file' => $this->renderFile($block, $options),
            'core/details' => $this->renderDetails($block, $inner, $options),
            'core/heading' => $this->renderHeading($block, $options),
            'core/icon' => $this->renderIcon($block, $options),
            'core/image' => $this->renderImage($block, $options),
            'core/math' => $this->renderMath($block, $options),
            'core/media-text' => $this->renderMediaText($block, $inner, $options),
            'core/video' => $this->renderVideo($block, $options),
            default => $this->renderStaticOrFallbackCoreBlock($block, $inner, $options),
        };
    }

    private function renderStaticOrFallbackCoreBlock(Block $block, string $inner, array $options): string
    {
        $html = trim($this->sanitize($this->renderStaticBlockMarkup($block, $options), $options));

        if ($html !== '') {
            $html = $this->postProcessStaticInnerBlocks($block, $html, $inner);
            $html = $this->applyStaticLayoutAttributes($block, $html);
            $html = $this->applyStaticWrapperSupportAttributes($block, $html);

            return $this->applyDuotone($block, $html);
        }

        if (! CoreBlocks::hasRuntimeFallback($block->name())) {
            return '';
        }

        return match ($block->name()) {
            'core/search' => $this->renderSearchFallback($block),
            'core/site-title' => $this->renderSiteTitleFallback($block),
            'core/site-tagline' => $this->renderSiteTaglineFallback($block),
            'core/latest-posts' => $this->renderLatestPostsFallback($block),
            'core/page-list', 'core/navigation' => $this->renderEntryListFallback($block),
            'core/calendar' => $this->renderCalendarFallback($block),
            'core/read-more' => $this->renderReadMoreFallback($block),
            'core/loginout' => $this->renderLoginoutFallback($block),
            'core/navigation-overlay-close' => $this->renderNavigationOverlayCloseFallback($block),
            default => $this->renderCoreFallbackNotice($block, $inner),
        };
    }

    private function renderStaticBlockMarkup(Block $block, array $options): string
    {
        $innerBlocks = $block->innerBlocks();

        if ($innerBlocks === []) {
            return $block->renderableHtml();
        }

        $html = '';
        $blockIndex = 0;

        foreach ($block->innerContent() as $content) {
            if (is_string($content)) {
                $html .= $content;

                continue;
            }

            if (isset($innerBlocks[$blockIndex])) {
                $html .= $this->renderBlock($innerBlocks[$blockIndex], $options);
            }

            $blockIndex++;
        }

        return $html;
    }

    private function postProcessStaticInnerBlocks(Block $block, string $html, string $inner): string
    {
        if ($inner === '' || trim($html) === '' || ! class_exists(DOMDocument::class)) {
            return $html;
        }

        return match ($block->name()) {
            'core/gallery' => $this->replaceGalleryInnerHtml($block, $html, $inner),
            default => $html,
        };
    }

    private function renderSearchFallback(Block $block): string
    {
        $label = trim((string) $block->attribute('label', 'Search')) ?: 'Search';
        $placeholder = (string) $block->attribute('placeholder', '');
        $button = trim((string) $block->attribute('buttonText', 'Search')) ?: 'Search';
        $showLabel = $this->truthy($block->attribute('showLabel', true));
        $buttonPosition = $this->searchButtonPosition((string) $block->attribute('buttonPosition', 'button-outside'));
        $hasButton = $buttonPosition !== 'no-button';
        $buttonUseIcon = $this->truthy($block->attribute('buttonUseIcon', false));
        $inputId = wp_unique_id('sgb-search-input-');
        $classes = ['wp-block-search', 'sgb-core-fallback-search'];
        $styles = $this->searchElementStyles($block, $buttonPosition, $showLabel);

        $classes[] = $buttonPosition === 'no-button'
            ? 'wp-block-search__no-button'
            : 'wp-block-search__'.$buttonPosition;

        if ($buttonPosition === 'button-only') {
            $classes[] = 'wp-block-search__searchfield-hidden';
        }

        if ($hasButton) {
            $classes[] = $buttonUseIcon ? 'wp-block-search__icon-button' : 'wp-block-search__text-button';
        }

        return sprintf(
            '<form role="search" method="get"%s><label%s>%s</label><div%s><input%s>%s%s</div></form>',
            $this->renderAttributes($this->searchRootAttributeArray($block, [
                'class' => implode(' ', $classes),
                'action' => $this->siteUrl('/search'),
            ])),
            $this->renderAttributes($this->searchLabelAttributeArray($block, $inputId, $showLabel, $styles['label'])),
            e($label),
            $this->renderAttributes([
                'class' => $this->mergeClasses(
                    'wp-block-search__inside-wrapper',
                    $buttonPosition === 'button-inside' ? $this->searchBorderColorClasses($block) : []
                ),
                'style' => $styles['wrapper'],
            ]),
            $this->renderAttributes($this->searchInputAttributeArray($block, $inputId, $placeholder, $buttonPosition, $styles['input'])),
            $this->searchHiddenInputs($block),
            $hasButton ? $this->searchButton($block, $button, $buttonUseIcon, $buttonPosition, $styles['button']) : '',
        );
    }

    private function searchButtonPosition(string $buttonPosition): string
    {
        return in_array($buttonPosition, ['button-inside', 'button-outside', 'button-only', 'no-button'], true)
            ? $buttonPosition
            : 'button-outside';
    }

    private function searchRootAttributeArray(Block $block, array $attributes): array
    {
        $classes = ['wp-block-search'];
        $align = $this->safeClassSlug($block->attribute('align'));

        if ($align) {
            $classes[] = 'align'.$align;
        }

        $classes = array_merge($classes, $this->safeClassList($block->attribute('className')));
        $classes = array_merge($classes, $this->safeClassList($attributes['class'] ?? ''));
        $attributes['class'] = implode(' ', array_values(array_unique(array_filter($classes))));

        $anchor = $this->safeAnchor($block->attribute('anchor'));

        if ($anchor !== '' && ! isset($attributes['id'])) {
            $attributes['id'] = $anchor;
        }

        $style = $block->attribute('style', []);
        $margin = is_array($style) ? $this->safeSpacingDeclarations('margin', $style['spacing']['margin'] ?? []) : [];

        if ($margin) {
            $attributes['style'] = $this->mergeStyles((string) ($attributes['style'] ?? ''), implode('; ', $margin));
        }

        return $attributes;
    }

    private function searchLabelAttributeArray(Block $block, string $inputId, bool $showLabel, string $style): array
    {
        $classes = ['wp-block-search__label'];

        if (! $showLabel) {
            $classes[] = 'sgb-screen-reader-text';
        } else {
            $classes = array_merge($classes, $this->searchTypographyClasses($block));
        }

        return [
            'class' => implode(' ', array_values(array_unique(array_filter($classes)))),
            'for' => $inputId,
            'style' => $style,
        ];
    }

    private function searchInputAttributeArray(Block $block, string $inputId, string $placeholder, string $buttonPosition, string $style): array
    {
        $classes = ['wp-block-search__input'];
        $classes = array_merge($classes, $this->searchTypographyClasses($block));

        if ($buttonPosition !== 'button-inside') {
            $classes = array_merge($classes, $this->searchBorderColorClasses($block));
        }

        if ($buttonPosition === 'no-button') {
            $classes = array_merge($classes, $this->searchColorClasses($block));
        }

        return [
            'class' => implode(' ', array_values(array_unique(array_filter($classes)))),
            'id' => $inputId,
            'type' => 'search',
            'name' => 'q',
            'value' => '',
            'placeholder' => $placeholder,
            'style' => $style,
        ];
    }

    private function searchElementStyles(Block $block, string $buttonPosition, bool $showLabel): array
    {
        $style = $block->attribute('style', []);
        $style = is_array($style) ? $style : [];
        $border = is_array($style['border'] ?? null) ? $style['border'] : [];
        $color = is_array($style['color'] ?? null) ? $style['color'] : [];
        $typography = is_array($style['typography'] ?? null) ? $style['typography'] : [];
        $isButtonInside = $buttonPosition === 'button-inside';
        $useInputForColors = $buttonPosition === 'no-button';
        $wrapperStyles = [];
        $inputStyles = [];
        $buttonStyles = [];
        $labelStyles = [];
        $width = $block->attribute('width');

        if (is_string($width) || is_numeric($width)) {
            $width = trim((string) $width);

            if ($width !== '' && preg_match('/^\d+(?:\.\d+)?$/', $width)) {
                $unit = (string) $block->attribute('widthUnit', '%');
                $unit = in_array($unit, ['%', 'px', 'em', 'rem', 'vw', 'vh'], true) ? $unit : '%';
                $wrapperStyles[] = 'width: '.$width.$unit;
            }
        }

        foreach (['width', 'color', 'style'] as $property) {
            foreach ($this->searchBorderDeclarations($border, $property) as $declaration) {
                if ($isButtonInside) {
                    $wrapperStyles[] = $declaration;
                } else {
                    $inputStyles[] = $declaration;
                    $buttonStyles[] = $declaration;
                }
            }
        }

        foreach ($this->searchBorderRadiusDeclarations($border['radius'] ?? null) as $declaration) {
            $inputStyles[] = $declaration;
            $buttonStyles[] = $declaration;

            if ($isButtonInside && preg_match('/^([^:]+):\s*(.+)$/', $declaration, $matches) && trim($matches[2]) !== '0') {
                $wrapperStyles[] = trim($matches[1]).': calc('.trim($matches[2]).' + 4px)';
            }
        }

        $colorTarget = $useInputForColors ? $inputStyles : $buttonStyles;

        if ($text = $this->safeStyleValue($color['text'] ?? null)) {
            $colorTarget[] = 'color: '.$text;
        }

        if ($background = $this->safeStyleValue($color['background'] ?? null)) {
            $colorTarget[] = 'background-color: '.$background;
        }

        if ($gradient = $this->safeStyleValue($color['gradient'] ?? null)) {
            $colorTarget[] = 'background: '.$gradient;
        }

        if ($useInputForColors) {
            $inputStyles = $colorTarget;
        } else {
            $buttonStyles = $colorTarget;
        }

        foreach ($this->searchTypographyDeclarations($typography) as $declaration) {
            $labelStyles[] = $declaration;
            $inputStyles[] = $declaration;
            $buttonStyles[] = $declaration;
        }

        if ($textDecoration = $this->safeStyleValue($typography['textDecoration'] ?? null)) {
            $buttonStyles[] = 'text-decoration: '.$textDecoration;

            if ($showLabel) {
                $labelStyles[] = 'text-decoration: '.$textDecoration;
            }
        }

        return [
            'wrapper' => implode('; ', array_values(array_filter($wrapperStyles))),
            'input' => implode('; ', array_values(array_filter($inputStyles))),
            'button' => implode('; ', array_values(array_filter($buttonStyles))),
            'label' => implode('; ', array_values(array_filter($labelStyles))),
        ];
    }

    private function searchBorderDeclarations(array $border, string $property): array
    {
        $declarations = [];

        foreach ([null, 'top', 'right', 'bottom', 'left'] as $side) {
            $source = $side === null ? $border : (is_array($border[$side] ?? null) ? $border[$side] : []);
            $value = $this->safeStyleValue($source[$property] ?? null);

            if (! $value) {
                continue;
            }

            $cssProperty = $side === null
                ? 'border-'.$property
                : 'border-'.$side.'-'.$property;

            $declarations[] = $cssProperty.': '.$value;
        }

        return $declarations;
    }

    private function searchBorderRadiusDeclarations(mixed $radius): array
    {
        if (is_string($radius) || is_numeric($radius)) {
            $value = $this->safeStyleValue(is_numeric($radius) ? $radius.'px' : $radius);

            return $value ? ['border-radius: '.$value] : [];
        }

        if (! is_array($radius)) {
            return [];
        }

        $map = [
            'topLeft' => 'border-top-left-radius',
            'top-left' => 'border-top-left-radius',
            'topRight' => 'border-top-right-radius',
            'top-right' => 'border-top-right-radius',
            'bottomLeft' => 'border-bottom-left-radius',
            'bottom-left' => 'border-bottom-left-radius',
            'bottomRight' => 'border-bottom-right-radius',
            'bottom-right' => 'border-bottom-right-radius',
        ];
        $declarations = [];

        foreach ($map as $key => $property) {
            $value = $this->safeStyleValue($radius[$key] ?? null);

            if ($value) {
                $declarations[$property] = $property.': '.$value;
            }
        }

        return array_values($declarations);
    }

    private function searchTypographyDeclarations(array $typography): array
    {
        $map = [
            'fontSize' => 'font-size',
            'fontFamily' => 'font-family',
            'letterSpacing' => 'letter-spacing',
            'fontWeight' => 'font-weight',
            'fontStyle' => 'font-style',
            'lineHeight' => 'line-height',
            'textTransform' => 'text-transform',
        ];
        $declarations = [];

        foreach ($map as $key => $property) {
            $value = $this->safeStyleValue($typography[$key] ?? null);

            if ($value) {
                $declarations[] = $property.': '.$value;
            }
        }

        return $declarations;
    }

    private function searchColorClasses(Block $block): array
    {
        $style = $block->attribute('style', []);
        $color = is_array($style) && is_array($style['color'] ?? null) ? $style['color'] : [];
        $classes = [];

        if ($textColor = $this->safeClassSlug($block->attribute('textColor'))) {
            $classes[] = 'has-text-color';
            $classes[] = 'has-'.$textColor.'-color';
        } elseif ($this->safeStyleValue($color['text'] ?? null)) {
            $classes[] = 'has-text-color';
        }

        if ($backgroundColor = $this->safeClassSlug($block->attribute('backgroundColor'))) {
            $classes[] = 'has-background';
            $classes[] = 'has-'.$backgroundColor.'-background-color';
        } elseif ($this->safeStyleValue($color['background'] ?? null)) {
            $classes[] = 'has-background';
        }

        if ($gradient = $this->safeClassSlug($block->attribute('gradient'))) {
            $classes[] = 'has-background';
            $classes[] = 'has-'.$gradient.'-gradient-background';
        } elseif ($this->safeStyleValue($color['gradient'] ?? null)) {
            $classes[] = 'has-background';
        }

        return array_values(array_unique($classes));
    }

    private function searchTypographyClasses(Block $block): array
    {
        $classes = [];

        if ($fontSize = $this->safeClassSlug($block->attribute('fontSize'))) {
            $classes[] = 'has-'.$fontSize.'-font-size';
        }

        if ($fontFamily = $this->safeClassSlug($block->attribute('fontFamily'))) {
            $classes[] = 'has-'.$fontFamily.'-font-family';
        }

        return $classes;
    }

    private function searchBorderColorClasses(Block $block): array
    {
        $style = $block->attribute('style', []);
        $border = is_array($style) && is_array($style['border'] ?? null) ? $style['border'] : [];
        $classes = [];

        if ($this->safeStyleValue($border['color'] ?? null)) {
            $classes[] = 'has-border-color';
        }

        if ($borderColor = $this->safeClassSlug($block->attribute('borderColor'))) {
            $classes[] = 'has-border-color';
            $classes[] = 'has-'.$borderColor.'-border-color';
        }

        return array_values(array_unique($classes));
    }

    private function searchHiddenInputs(Block $block): string
    {
        $query = $block->attribute('query', []);

        if (! is_array($query)) {
            return '';
        }

        $inputs = [];

        foreach ($query as $name => $value) {
            if (! is_string($name) || ! preg_match('/^[A-Za-z0-9_.-]+$/', $name)) {
                continue;
            }

            if (! is_string($value) && ! is_numeric($value) && ! is_bool($value)) {
                continue;
            }

            $inputs[] = sprintf(
                '<input type="hidden" name="%s" value="%s">',
                e($name),
                e(is_bool($value) ? ($value ? '1' : '0') : (string) $value),
            );
        }

        return implode('', $inputs);
    }

    private function searchButton(Block $block, string $button, bool $useIcon, string $buttonPosition, string $style): string
    {
        $content = $useIcon
            ? $this->searchIcon().'<span class="sgb-screen-reader-text">'.e($button).'</span>'
            : e($button);
        $classes = ['wp-block-search__button', 'wp-element-button'];
        $classes = array_merge($classes, $this->searchColorClasses($block), $this->searchTypographyClasses($block));

        if ($buttonPosition !== 'button-inside') {
            $classes = array_merge($classes, $this->searchBorderColorClasses($block));
        }

        return sprintf(
            '<button%s>%s</button>',
            $this->renderAttributes([
                'class' => implode(' ', array_values(array_unique(array_filter($classes)))),
                'type' => 'submit',
                'aria-label' => $useIcon ? $button : '',
                'style' => $style,
            ]),
            $content,
        );
    }

    private function searchIcon(): string
    {
        return '<svg class="search-icon" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="M13 5c-3.3 0-6 2.7-6 6 0 1.4.5 2.7 1.3 3.7l-3.5 3.5 1.1 1.1 3.5-3.5c1 .8 2.3 1.3 3.7 1.3 3.3 0 6-2.7 6-6S16.3 5 13 5Zm0 1.5c2.5 0 4.5 2 4.5 4.5s-2 4.5-4.5 4.5-4.5-2-4.5-4.5 2-4.5 4.5-4.5Z"></path></svg>';
    }

    private function renderSiteTitleFallback(Block $block): string
    {
        $level = max(0, min(6, (int) $block->attribute('level', 1)));
        $tag = $level === 0 ? 'p' : 'h'.$level;
        $title = trim((string) config('app.name', 'Site title')) ?: 'Site title';
        $content = e($title);

        if ($this->truthy($block->attribute('isLink', true))) {
            $content = sprintf(
                '<a href="%s"%s>%s</a>',
                e($this->siteUrl('/')),
                $this->renderAttributes(['target' => (string) $block->attribute('linkTarget', '_self')]),
                $content
            );
        }

        return sprintf('<%s%s>%s</%s>', $tag, $this->fallbackRootAttributes($block, [
            'class' => 'wp-block-site-title',
        ]), $content, $tag);
    }

    private function renderSiteTaglineFallback(Block $block): string
    {
        $level = max(0, min(6, (int) $block->attribute('level', 0)));
        $tag = $level === 0 ? 'p' : 'h'.$level;
        $tagline = trim((string) config('statamic.system.tagline', '')) ?: trim((string) config('app.description', ''));
        $tagline = $tagline !== '' ? $tagline : 'Site tagline';

        return sprintf('<%s%s>%s</%s>', $tag, $this->fallbackRootAttributes($block, [
            'class' => 'wp-block-site-tagline',
        ]), e($tagline), $tag);
    }

    private function renderLatestPostsFallback(Block $block): string
    {
        $limit = max(1, min(20, (int) $block->attribute('postsToShow', 5)));
        $entries = $this->latestEntries($limit);

        if ($entries === []) {
            return $this->renderCoreFallbackNotice($block);
        }

        $items = collect($entries)
            ->map(fn ($entry) => sprintf(
                '<li><a href="%s">%s</a></li>',
                e($this->entryUrl($entry)),
                e($this->entryTitle($entry))
            ))
            ->implode('');

        return sprintf('<ul%s>%s</ul>', $this->fallbackRootAttributes($block, [
            'class' => 'wp-block-latest-posts__list wp-block-latest-posts',
        ]), $items);
    }

    private function renderEntryListFallback(Block $block): string
    {
        $entries = $this->latestEntries(12);

        if ($entries === []) {
            return $this->renderCoreFallbackNotice($block);
        }

        $class = $block->name() === 'core/navigation'
            ? 'wp-block-navigation__container wp-block-navigation'
            : 'wp-block-page-list';

        $items = collect($entries)
            ->map(fn ($entry) => sprintf(
                '<li class="wp-block-pages-list__item"><a class="wp-block-pages-list__item__link" href="%s">%s</a></li>',
                e($this->entryUrl($entry)),
                e($this->entryTitle($entry))
            ))
            ->implode('');

        return sprintf('<ul%s>%s</ul>', $this->fallbackRootAttributes($block, [
            'class' => $class,
        ]), $items);
    }

    private function renderCalendarFallback(Block $block): string
    {
        $month = (int) ($block->attribute('month') ?: date('n'));
        $year = (int) ($block->attribute('year') ?: date('Y'));
        $month = max(1, min(12, $month));
        $timestamp = mktime(0, 0, 0, $month, 1, $year);
        $days = (int) date('t', $timestamp);
        $firstWeekday = (int) date('N', $timestamp);
        $caption = date('F Y', $timestamp);
        $weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $cells = array_fill(0, $firstWeekday - 1, '<td></td>');

        for ($day = 1; $day <= $days; $day++) {
            $cells[] = '<td>'.e((string) $day).'</td>';
        }

        while (count($cells) % 7 !== 0) {
            $cells[] = '<td></td>';
        }

        $rows = collect(array_chunk($cells, 7))
            ->map(fn ($row) => '<tr>'.implode('', $row).'</tr>')
            ->implode('');

        $head = collect($weekdays)
            ->map(fn ($day) => '<th scope="col">'.e($day).'</th>')
            ->implode('');

        return sprintf(
            '<table%s><caption>%s</caption><thead><tr>%s</tr></thead><tbody>%s</tbody></table>',
            $this->fallbackRootAttributes($block, [
                'class' => 'wp-block-calendar wp-calendar-table',
            ]),
            e($caption),
            $head,
            $rows
        );
    }

    private function renderReadMoreFallback(Block $block): string
    {
        $content = trim((string) $block->attribute('content', 'Read more')) ?: 'Read more';

        return sprintf(
            '<a%s href="%s"%s>%s</a>',
            $this->fallbackRootAttributes($block, [
                'class' => 'wp-block-read-more',
            ]),
            e($this->currentUrl()),
            $this->renderAttributes(['target' => (string) $block->attribute('linkTarget', '_self')]),
            e($content)
        );
    }

    private function renderMoreMarker(Block $block, array $options): string
    {
        $savedHtml = trim($this->sanitize($block->renderableHtml(), $options));

        if ($savedHtml !== '') {
            return $savedHtml;
        }

        $customText = $this->safeCommentText((string) $block->attribute('customText', ''));
        $marker = $customText !== '' ? '<!--more '.$customText.'-->' : '<!--more-->';

        if ($this->truthy($block->attribute('noTeaser', false))) {
            $marker .= "\n<!--noteaser-->";
        }

        return $marker;
    }

    private function renderNextPageMarker(Block $block, array $options): string
    {
        $savedHtml = trim($this->sanitize($block->renderableHtml(), $options));

        if ($savedHtml !== '') {
            return $savedHtml;
        }

        return '<!--nextpage-->';
    }

    private function safeCommentText(string $value): string
    {
        $value = strip_tags($value);
        $value = str_replace(['--', '<', '>'], ['-', '', ''], $value);
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function renderLoginoutFallback(Block $block): string
    {
        return sprintf('<a%s href="%s">Log in</a>', $this->fallbackRootAttributes($block, [
            'class' => 'wp-block-loginout',
        ]), e($this->siteUrl('/login')));
    }

    private function renderNavigationOverlayCloseFallback(Block $block): string
    {
        return sprintf('<button%s type="button">Close</button>', $this->fallbackRootAttributes($block, [
            'class' => 'wp-block-navigation__responsive-container-close',
        ]));
    }

    private function renderReusableBlock(Block $block, array $options): string
    {
        $ref = (int) $block->attribute('ref', 0);

        if ($ref <= 0) {
            return trim($this->sanitize($block->renderableHtml(), $options));
        }

        $seen = array_map('intval', $options['_pattern_refs'] ?? []);

        if (in_array($ref, $seen, true)) {
            return '';
        }

        $pattern = $this->patterns->findRenderablePattern($ref);

        if (! $pattern) {
            return '';
        }

        $options['_pattern_refs'] = [...$seen, $ref];

        return $this->renderBlocks($this->parser->parse($pattern['content'] ?? ''), $options);
    }

    private function renderCoreFallbackNotice(Block $block, string $inner = ''): string
    {
        $class = $this->coreBlockClass($block->name()).' sgb-core-fallback';

        if ($inner !== '') {
            return sprintf(
                '<div%s>%s</div>',
                $this->fallbackRootAttributes($block, [
                    'class' => $class,
                    'data-sgb-core-fallback' => $block->name(),
                ]),
                $inner
            );
        }

        return sprintf(
            '<div%s><strong>%s</strong><span>%s</span></div>',
            $this->fallbackRootAttributes($block, [
                'class' => $class,
                'data-sgb-core-fallback' => $block->name(),
            ]),
            e($this->coreBlockTitle($block->name())),
            e('No Statamic data available.')
        );
    }

    private function latestEntries(int $limit): array
    {
        try {
            return collect(Entry::query()
                ->where('published', true)
                ->orderBy('date', 'desc')
                ->limit($limit)
                ->get())
                ->values()
                ->all();
        } catch (Throwable) {
            //
        }

        try {
            return Entry::all()
                ->sortByDesc(fn ($entry) => $this->entryTimestamp($entry))
                ->take($limit)
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function entryTitle(mixed $entry): string
    {
        try {
            if (is_object($entry) && method_exists($entry, 'title')) {
                return (string) ($entry->title() ?: 'Untitled');
            }

            if (is_object($entry) && method_exists($entry, 'get')) {
                return (string) ($entry->get('title') ?: 'Untitled');
            }
        } catch (Throwable) {
            //
        }

        return 'Untitled';
    }

    private function entryUrl(mixed $entry): string
    {
        try {
            if (is_object($entry) && method_exists($entry, 'url')) {
                return (string) ($entry->url() ?: '#');
            }
        } catch (Throwable) {
            //
        }

        return '#';
    }

    private function entryTimestamp(mixed $entry): int
    {
        try {
            if (is_object($entry) && method_exists($entry, 'date') && $entry->date()) {
                return $entry->date()->getTimestamp();
            }

            if (is_object($entry) && method_exists($entry, 'lastModified') && $entry->lastModified()) {
                return $entry->lastModified()->getTimestamp();
            }
        } catch (Throwable) {
            //
        }

        return 0;
    }

    private function coreBlockClass(string $name): string
    {
        $slug = str_starts_with($name, 'core/') ? substr($name, 5) : str_replace('/', '-', $name);

        return 'wp-block-'.str_replace('_', '-', $slug);
    }

    private function coreBlockTitle(string $name): string
    {
        $slug = str_starts_with($name, 'core/') ? substr($name, 5) : $name;

        return ucwords(str_replace(['-', '_', '/'], ' ', $slug));
    }

    private function fallbackRootAttributes(Block $block, array $attributes, bool $includeBaseClass = true): string
    {
        return BlockWrapperContext::withBlock(
            $block,
            fn (): string => BlockWrapperContext::wrapperAttributes($attributes, $includeBaseClass)
        );
    }

    private function fallbackRootAttributeArray(Block $block, array $attributes, bool $includeBaseClass = true): array
    {
        return BlockWrapperContext::attributesForBlock($block, $attributes, $includeBaseClass);
    }

    private function siteUrl(string $path = '/'): string
    {
        try {
            return url($path);
        } catch (Throwable) {
            return $path;
        }
    }

    private function currentUrl(): string
    {
        try {
            return request()->url();
        } catch (Throwable) {
            return '#';
        }
    }

    private function safeTagName(string $tag, string $fallback, array $allowed): string
    {
        $tag = strtolower($tag);

        return in_array($tag, $allowed, true) ? $tag : $fallback;
    }

    private function renderWrapperBlock(
        Block $block,
        string $inner,
        array $options,
        string $fallbackTag,
        string $baseClass,
        array $allowedTags = ['div'],
        array $extraAttributes = []
    ): string {
        $renderableHtml = $this->sanitize($block->renderableHtml(), $options);
        $fragment = $this->firstElementFragment($renderableHtml);
        $hasSavedWrapper = $fragment && $this->fragmentHasClass($fragment, $baseClass);
        $tag = $hasSavedWrapper && in_array($fragment['tag'], $allowedTags, true) ? $fragment['tag'] : $fallbackTag;
        $attributes = $hasSavedWrapper
            ? $this->fallbackRootAttributeArray($block, array_merge($fragment['attributes'], $extraAttributes))
            : $this->fallbackRootAttributeArray($block, array_merge(['class' => $baseClass], $extraAttributes));
        $attributes = $this->applyLayoutAttributes($block, $attributes, $baseClass);
        $hasParsedInnerBlocks = count($block->innerBlocks()) > 0;
        $content = $inner !== ''
            ? $inner
            : ($hasParsedInnerBlocks ? '' : ($hasSavedWrapper ? (string) $fragment['html'] : $renderableHtml));

        return sprintf(
            '<%s%s>%s</%s>',
            $tag,
            $this->renderAttributes($attributes),
            $content,
            $tag
        );
    }

    private function applyLayoutAttributes(Block $block, array $attributes, string $baseClass): array
    {
        $layout = $block->attribute('layout', []);
        $classes = [$baseClass];
        $styles = $this->layoutVariableStyles(is_array($layout) ? $layout : []);

        if ($baseClass === 'wp-block-group' && is_array($layout)) {
            $type = (string) ($layout['type'] ?? '');

            if ($type === 'constrained') {
                $classes[] = 'is-layout-constrained';
                $classes[] = 'wp-block-group-is-layout-constrained';
            } elseif ($type === 'flex') {
                $classes[] = 'is-layout-flex';
                $classes[] = 'wp-block-group-is-layout-flex';
                $styles[] = 'display: flex';
                $styles[] = 'align-items: flex-start';
                $styles[] = 'justify-content: '.$this->cssJustification((string) ($layout['justifyContent'] ?? $layout['contentJustification'] ?? 'left'));
                $styles[] = 'gap: var(--wp--style--block-gap)';

                if (($layout['orientation'] ?? '') === 'vertical') {
                    $classes[] = 'is-vertical';
                    $styles[] = 'flex-direction: column';
                } else {
                    $styles[] = 'flex-direction: row';
                }

                if (($layout['flexWrap'] ?? '') === 'nowrap') {
                    $classes[] = 'is-nowrap';
                    $styles[] = 'flex-wrap: nowrap';
                } else {
                    $styles[] = 'flex-wrap: wrap';
                }
            } elseif ($type === 'grid') {
                $classes[] = 'is-layout-grid';
                $classes[] = 'wp-block-group-is-layout-grid';
                $styles[] = 'display: grid';
                $styles[] = 'gap: var(--wp--style--block-gap)';
                $styles[] = 'align-items: flex-start';

                $columnCount = (int) ($layout['columnCount'] ?? 0);
                $minimumColumnWidth = $this->safeLayoutSize($layout['minimumColumnWidth'] ?? null);

                if ($columnCount > 0) {
                    $styles[] = 'grid-template-columns: repeat('.min(12, $columnCount).', minmax(0, 1fr))';
                } elseif ($minimumColumnWidth !== null) {
                    $styles[] = 'grid-template-columns: repeat(auto-fill, minmax(min('.$minimumColumnWidth.', 100%), 1fr))';
                }
            }
        } elseif ($baseClass === 'wp-block-columns') {
            $alignment = $this->safeClassSlug($block->attribute('verticalAlignment'));

            if ($alignment && in_array($alignment, ['top', 'center', 'bottom'], true)) {
                $classes[] = 'are-vertically-aligned-'.$alignment;
            }

            if ($this->explicitlyFalse($block->attribute('isStackedOnMobile', true))) {
                $classes[] = 'is-not-stacked-on-mobile';
            }
        } elseif ($baseClass === 'wp-block-column') {
            $alignment = $this->safeClassSlug($block->attribute('verticalAlignment'));

            if ($alignment && in_array($alignment, ['top', 'center', 'bottom', 'stretch'], true)) {
                $classes[] = 'is-vertically-aligned-'.$alignment;
            }

            $width = $this->safeStyleValue($block->attribute('width'));

            if ($width) {
                $styles[] = 'flex-basis: '.$width;
            }
        }

        $attributes['class'] = $this->mergeClasses($attributes['class'] ?? '', $classes);
        $attributes['style'] = $this->mergeStyles($attributes['style'] ?? '', implode('; ', $styles));

        return $attributes;
    }

    private function applyStaticLayoutAttributes(Block $block, string $html): string
    {
        $layout = $block->attribute('layout', []);

        if (! is_array($layout)) {
            return $html;
        }

        return match ($block->name()) {
            'core/cover' => $this->applyCoverLayoutAttributes($html, $layout),
            default => $html,
        };
    }

    private function applyStaticWrapperSupportAttributes(Block $block, string $html): string
    {
        $tags = self::STATIC_WRAPPER_SUPPORT_TAGS[$block->name()] ?? null;

        if (! $tags) {
            return $html;
        }

        $includeBaseClass = ! in_array($block->name(), self::BASE_CLASSLESS_STATIC_BLOCKS, true);

        return $this->transformFirstElement($html, $tags, function (DOMDocument $document, DOMElement $element) use ($block, $includeBaseClass): void {
            $attributes = $this->fallbackRootAttributeArray(
                $block,
                $this->elementAttributes($element),
                $includeBaseClass
            );

            $this->replaceElementAttributes($element, $attributes);
        });
    }

    private function applyCoverLayoutAttributes(string $html, array $layout): string
    {
        if (($layout['type'] ?? '') !== 'constrained' || trim($html) === '' || ! class_exists(DOMDocument::class)) {
            return $html;
        }

        $document = $this->loadFragment($html);
        $wrapper = $document->getElementById('__statamic_gutenberg_fragment__');

        if (! $wrapper) {
            return $html;
        }

        foreach ($wrapper->childNodes as $child) {
            if (! $child instanceof DOMElement || ! $this->elementHasClass($child, 'wp-block-cover')) {
                continue;
            }

            foreach ($child->getElementsByTagName('div') as $innerContainer) {
                if (! $innerContainer instanceof DOMElement || ! $this->elementHasClass($innerContainer, 'wp-block-cover__inner-container')) {
                    continue;
                }

                $this->addClasses($innerContainer, ['is-layout-constrained', 'wp-block-cover-is-layout-constrained']);
                $this->addLayoutVariableStyles($innerContainer, $layout);

                return $this->fragmentHtml($document, $wrapper);
            }
        }

        return $html;
    }

    private function replaceFirstDescendantInnerHtml(string $html, string $tagName, string $className, string $inner): string
    {
        $document = $this->loadFragment($html);
        $wrapper = $document->getElementById('__statamic_gutenberg_fragment__');

        if (! $wrapper) {
            return $html;
        }

        foreach ($wrapper->getElementsByTagName($tagName) as $element) {
            if (! $element instanceof DOMElement || ! $this->elementHasClass($element, $className)) {
                continue;
            }

            $this->replaceElementInnerHtml($document, $element, $inner);

            return $this->fragmentHtml($document, $wrapper);
        }

        return $html;
    }

    private function replaceGalleryInnerHtml(Block $block, string $html, string $inner): string
    {
        $document = $this->loadFragment($html);
        $wrapper = $document->getElementById('__statamic_gutenberg_fragment__');

        if (! $wrapper) {
            return $html;
        }

        foreach ($wrapper->getElementsByTagName('figure') as $gallery) {
            if (! $gallery instanceof DOMElement || ! $this->elementHasClass($gallery, 'wp-block-gallery')) {
                continue;
            }

            $captions = [];

            foreach (iterator_to_array($gallery->childNodes) as $child) {
                if ($child instanceof DOMElement && strtolower($child->tagName) === 'figcaption') {
                    $captions[] = $child->cloneNode(true);
                }
            }

            $this->replaceElementInnerHtml($document, $gallery, $inner);
            $this->addGalleryGapStyles($block, $gallery);

            foreach ($captions as $caption) {
                $gallery->appendChild($caption);
            }

            return $this->fragmentHtml($document, $wrapper);
        }

        return $html;
    }

    private function addGalleryGapStyles(Block $block, DOMElement $gallery): void
    {
        $style = $block->attribute('style', []);
        $blockGap = is_array($style) ? $this->safeStyleValue($style['spacing']['blockGap'] ?? null) : null;

        if (! $blockGap) {
            return;
        }

        $gallery->setAttribute('style', $this->mergeStyles(
            $gallery->getAttribute('style'),
            '--wp--style--unstable-gallery-gap: '.$blockGap.'; gap: '.$blockGap
        ));
    }

    private function replaceElementInnerHtml(DOMDocument $document, DOMElement $element, string $inner): void
    {
        while ($element->firstChild) {
            $element->removeChild($element->firstChild);
        }

        $innerDocument = $this->loadFragment($inner);
        $innerWrapper = $innerDocument->getElementById('__statamic_gutenberg_fragment__');

        if (! $innerWrapper) {
            return;
        }

        foreach (iterator_to_array($innerWrapper->childNodes) as $child) {
            $element->appendChild($document->importNode($child, true));
        }
    }

    private function layoutVariableStyles(array $layout): array
    {
        $styles = [];
        $contentSize = $this->safeLayoutSize($layout['contentSize'] ?? null);
        $wideSize = $this->safeLayoutSize($layout['wideSize'] ?? null);

        if ($contentSize !== null) {
            $styles[] = '--wp--style--global--content-size: '.$contentSize;
        }

        if ($wideSize !== null) {
            $styles[] = '--wp--style--global--wide-size: '.$wideSize;
        }

        return $styles;
    }

    private function addLayoutVariableStyles(DOMElement $element, array $layout): void
    {
        $styles = implode('; ', $this->layoutVariableStyles($layout));

        if ($styles === '') {
            return;
        }

        $element->setAttribute('style', $this->mergeStyles($element->getAttribute('style'), $styles));
    }

    private function safeLayoutSize(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || preg_match('/(?:url|expression|javascript|;|{|}|<|>)/i', $value)) {
            return null;
        }

        return preg_match('/^[a-z0-9_.,%()+\-*\/\s]+$/i', $value) ? $value : null;
    }

    private function safeStyleValue(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || preg_match('/[;{}<>]/', $value)) {
            return null;
        }

        if (preg_match('/(?:expression|javascript:|vbscript:|data:|url\s*\()/i', $value)) {
            return null;
        }

        if (preg_match('/^var:preset\|([a-z0-9_-]+)\|([a-z0-9_-]+)$/i', $value, $matches)) {
            return sprintf('var(--wp--preset--%s--%s)', $matches[1], $matches[2]);
        }

        return $value;
    }

    private function cssJustification(string $value): string
    {
        return match ($value) {
            'center' => 'center',
            'right' => 'flex-end',
            'space-between' => 'space-between',
            default => 'flex-start',
        };
    }

    private function renderHeading(Block $block, array $options): string
    {
        $html = trim($block->renderableHtml());
        $classes = ['wp-block-heading'];

        if ($this->truthy($block->attribute('fitText', false))) {
            $classes[] = 'has-fit-text';
        }

        if ($html === '') {
            $content = trim((string) $block->attribute('content', ''));

            if ($content === '') {
                return '';
            }

            $level = max(1, min(6, (int) $block->attribute('level', 2)));

            return $this->sanitize(sprintf(
                '<h%d%s>%s</h%d>',
                $level,
                $this->fallbackRootAttributes($block, [
                    'class' => implode(' ', $classes),
                ]),
                $content,
                $level
            ), $options);
        }

        $html = $this->addClassesToFirstElement(
            $this->sanitize($html, $options),
            $classes,
            ['h1', 'h2', 'h3', 'h4', 'h5', 'h6']
        );

        return $this->applyStaticWrapperSupportAttributes($block, $html);
    }

    private function renderImage(Block $block, array $options): string
    {
        $html = trim($block->renderableHtml());

        if ($html === '') {
            $html = $this->renderConstructedImage($block);
        }

        if ($html === '') {
            return '';
        }

        $html = $this->addClassesToFirstElement($this->sanitize($html, $options), ['wp-block-image'], ['figure']);
        $lightbox = $block->attribute('lightbox', []);

        if (is_array($lightbox) && $this->truthy($lightbox['enabled'] ?? false)) {
            $html = $this->enableImageLightbox($html);
        }

        return $this->applyDuotone($block, $html);
    }

    private function renderVideo(Block $block, array $options): string
    {
        $html = trim($block->renderableHtml());

        if ($html === '') {
            $src = $block->attribute('src');

            if (is_scalar($src) && trim((string) $src) !== '') {
                $caption = trim((string) $block->attribute('caption', ''));
                $tracks = $this->renderVideoTracks($block->attribute('tracks', []));
                $html = sprintf(
                    '<figure%s><video%s>%s</video>%s</figure>',
                    $this->fallbackRootAttributes($block, [
                        'class' => 'wp-block-video',
                    ]),
                    $this->renderAttributes([
                        'controls' => $this->explicitlyFalse($block->attribute('controls')) ? '' : 'controls',
                        'autoplay' => $this->truthy($block->attribute('autoplay', false)) ? 'autoplay' : '',
                        'loop' => $this->truthy($block->attribute('loop', false)) ? 'loop' : '',
                        'muted' => $this->truthy($block->attribute('muted', false)) ? 'muted' : '',
                        'poster' => is_scalar($block->attribute('poster')) ? trim((string) $block->attribute('poster')) : '',
                        'preload' => $this->safeMediaPreload($block->attribute('preload')),
                        'src' => (string) $src,
                        'playsinline' => $this->truthy($block->attribute('playsInline', false)) ? 'playsinline' : '',
                    ]),
                    $tracks,
                    $caption !== '' ? '<figcaption class="wp-element-caption">'.e($caption).'</figcaption>' : ''
                );
            }
        }

        if ($html === '') {
            return '';
        }

        $html = $this->sanitize($html, $options);

        $html = $this->transformFirstElement($html, ['figure', 'video'], function (DOMDocument $document, DOMElement $element) use ($block): void {
            if (strtolower($element->tagName) === 'figure') {
                $this->addClasses($element, ['wp-block-video']);
            }

            $video = strtolower($element->tagName) === 'video'
                ? $element
                : $element->getElementsByTagName('video')->item(0);

            if (! $video instanceof DOMElement) {
                return;
            }

            if ($this->explicitlyFalse($block->attribute('controls'))) {
                $video->removeAttribute('controls');

                return;
            }

            $video->setAttribute('controls', 'controls');
        });

        return $this->applyStaticWrapperSupportAttributes($block, $html);
    }

    private function renderAudio(Block $block, array $options): string
    {
        $html = trim($block->renderableHtml());

        if ($html === '') {
            $src = $block->attribute('src');

            if (is_scalar($src) && trim((string) $src) !== '') {
                $caption = trim((string) $block->attribute('caption', ''));
                $html = sprintf(
                    '<figure%s><audio%s></audio>%s</figure>',
                    $this->fallbackRootAttributes($block, [
                        'class' => 'wp-block-audio',
                    ]),
                    $this->renderAttributes([
                        'controls' => 'controls',
                        'src' => (string) $src,
                        'autoplay' => $this->truthy($block->attribute('autoplay', false)) ? 'autoplay' : '',
                        'loop' => $this->truthy($block->attribute('loop', false)) ? 'loop' : '',
                        'preload' => $this->safeMediaPreload($block->attribute('preload')),
                    ]),
                    $caption !== '' ? '<figcaption class="wp-element-caption">'.e($caption).'</figcaption>' : ''
                );
            }
        }

        if ($html === '') {
            return '';
        }

        $html = $this->sanitize($html, $options);

        $html = $this->transformFirstElement($html, ['figure', 'audio'], function (DOMDocument $document, DOMElement $element): void {
            if (strtolower($element->tagName) === 'figure') {
                $this->addClasses($element, ['wp-block-audio']);
            }

            $audio = strtolower($element->tagName) === 'audio'
                ? $element
                : $element->getElementsByTagName('audio')->item(0);

            if ($audio instanceof DOMElement) {
                $audio->setAttribute('controls', 'controls');
            }
        });

        return $this->applyStaticWrapperSupportAttributes($block, $html);
    }

    private function renderSpacer(Block $block, array $options): string
    {
        $html = trim($block->renderableHtml());

        if ($html !== '') {
            $html = $this->addClassesToFirstElement($this->sanitize($html, $options), ['wp-block-spacer'], ['div']);

            return $this->applyStaticWrapperSupportAttributes($block, $html);
        }

        $attributes = $block->attributes();
        $style = $block->attribute('style', []);
        $selfStretch = is_array($style) ? (string) ($style['layout']['selfStretch'] ?? '') : '';
        $heightValue = array_key_exists('height', $attributes) || ! in_array($selfStretch, ['fill', 'fit'], true)
            ? $this->safeStyleValue($block->attribute('height', '100px'))
            : null;
        $widthValue = $this->safeStyleValue($block->attribute('width'));
        $styles = array_filter([
            $heightValue ? 'height: '.$heightValue : null,
            $widthValue ? 'width: '.$widthValue : null,
        ]);

        return '<div'.$this->fallbackRootAttributes($block, [
            'class' => 'wp-block-spacer',
            'aria-hidden' => 'true',
            'style' => implode('; ', $styles),
        ]).'></div>';
    }

    private function renderSeparator(Block $block, array $options): string
    {
        $html = trim($block->renderableHtml());

        if ($html !== '') {
            $html = $this->addClassesToFirstElement($this->sanitize($html, $options), ['wp-block-separator'], ['hr', 'div']);

            return $this->applyStaticWrapperSupportAttributes($block, $html);
        }

        $tag = $this->safeTagName((string) $block->attribute('tagName', 'hr'), 'hr', ['hr', 'div']);
        $classes = ['wp-block-separator'];
        $align = $this->safeClassSlug($block->attribute('align'));

        if ($align && in_array($align, ['center', 'wide', 'full'], true)) {
            $classes[] = 'align'.$align;
        }

        array_push($classes, ...$this->safeClassList($block->attribute('className')));

        $opacity = (string) $block->attribute('opacity', 'alpha-channel');

        if ($opacity === 'css') {
            $classes[] = 'has-css-opacity';
        } elseif ($opacity === 'alpha-channel') {
            $classes[] = 'has-alpha-channel-opacity';
        }

        $backgroundColor = $this->safeClassSlug($block->attribute('backgroundColor'));

        if ($backgroundColor) {
            $classes[] = "has-{$backgroundColor}-color";
            $classes[] = 'has-text-color';
        }

        $style = $block->attribute('style', []);
        $styleDeclarations = [];
        $customColor = is_array($style) ? $this->safeStyleValue($style['color']['background'] ?? null) : null;

        if ($customColor) {
            $styleDeclarations[] = 'color: '.$customColor;
            $classes[] = 'has-text-color';
        }

        $styleDeclarations = array_merge($styleDeclarations, $this->safeSpacingDeclarations('margin', is_array($style) ? ($style['spacing']['margin'] ?? []) : []));

        return sprintf(
            '<%s%s%s>',
            $tag,
            $this->renderAttributes([
                'id' => $this->safeAnchor($block->attribute('anchor')),
                'class' => implode(' ', array_values(array_unique(array_filter($classes)))),
                'style' => implode('; ', $styleDeclarations),
            ]),
            $tag === 'hr' ? ' /' : ''
        ).($tag === 'div' ? '</div>' : '');
    }

    private function renderMath(Block $block, array $options): string
    {
        $html = trim($block->renderableHtml());

        if ($html === '') {
            $latex = trim((string) $block->attribute('latex', ''));
            $mathML = trim((string) $block->attribute('mathML', ''));

            if ($latex === '' || $mathML === '') {
                return '';
            }

            $math = str_starts_with(ltrim($mathML), '<math')
                ? $mathML
                : '<math display="block">'.$mathML.'</math>';
            $html = '<div class="wp-block-math">'.$math.'</div>';
        }

        $html = $this->addClassesToFirstElement($this->sanitize($html, $options), ['wp-block-math'], ['div']);

        return $this->applyStaticWrapperSupportAttributes($block, $html);
    }

    private function renderFile(Block $block, array $options): string
    {
        $html = trim($block->renderableHtml());

        if ($html !== '') {
            $html = $this->addClassesToFirstElement($this->sanitize($html, $options), ['wp-block-file'], ['div']);

            return $this->applyStaticWrapperSupportAttributes($block, $html);
        }

        $href = $block->attribute('href');

        if (! is_scalar($href) || trim((string) $href) === '') {
            return '';
        }

        $href = trim((string) $href);
        $fileName = trim(wp_strip_all_tags((string) $block->attribute('fileName', '')));
        $fileName = $fileName !== '' ? $fileName : $this->fileNameFromUrl($href);
        $fileId = $this->safeAnchor($block->attribute('fileId')) ?: 'wp-block-file--media-'.substr(md5($href), 0, 12);
        $textLinkHref = $block->attribute('textLinkHref');
        $textLinkHref = is_scalar($textLinkHref) && trim((string) $textLinkHref) !== '' ? trim((string) $textLinkHref) : $href;
        $target = $this->safeLinkTarget($block->attribute('textLinkTarget'));
        $previewHeight = max(120, min(2000, (int) $block->attribute('previewHeight', 600)));
        $downloadText = trim(wp_strip_all_tags((string) $block->attribute('downloadButtonText', '')));
        $downloadText = $downloadText !== '' ? $downloadText : 'Download';
        $inner = '';

        if ($this->truthy($block->attribute('displayPreview', false))) {
            $inner .= '<object'.$this->renderAttributes([
                'class' => 'wp-block-file__embed',
                'data' => $href,
                'type' => 'application/pdf',
                'style' => 'width: 100%; height: '.$previewHeight.'px',
                'aria-label' => $fileName !== '' ? $fileName : 'PDF embed',
            ]).'></object>';
        }

        if ($fileName !== '') {
            $inner .= '<a'.$this->renderAttributes([
                'id' => $fileId,
                'href' => $textLinkHref,
                'target' => $target,
                'rel' => $target === '_blank' ? 'noreferrer noopener' : '',
            ]).'>'.e($fileName).'</a>';
        }

        if (! $this->explicitlyFalse($block->attribute('showDownloadButton', true))) {
            $inner .= '<a'.$this->renderAttributes([
                'href' => $href,
                'class' => 'wp-block-file__button wp-element-button',
                'download' => 'download',
                'aria-describedby' => $fileName !== '' ? $fileId : '',
            ]).'>'.e($downloadText).'</a>';
        }

        return $this->sanitize('<div'.$this->fallbackRootAttributes($block, [
            'class' => 'wp-block-file',
        ]).'>'.$inner.'</div>', $options);
    }

    private function renderDetails(Block $block, string $inner, array $options): string
    {
        $html = trim($this->sanitize($this->renderStaticBlockMarkup($block, $options), $options));

        if ($this->firstElementHasClass($html, 'wp-block-details')) {
            return $this->applyStaticWrapperSupportAttributes($block, $html);
        }

        $summary = trim((string) $block->attribute('summary', ''));
        $summary = $summary !== '' ? $summary : 'Details';
        $name = $block->attribute('name');
        $attributes = [
            'class' => 'wp-block-details',
            'name' => is_scalar($name) ? trim((string) $name) : '',
            'open' => $this->truthy($block->attribute('showContent', false)) ? 'open' : '',
        ];

        return $this->sanitize('<details'.$this->fallbackRootAttributes($block, $attributes).'><summary>'.$summary.'</summary>'.$inner.'</details>', $options);
    }

    private function renderMediaText(Block $block, string $inner, array $options): string
    {
        $html = trim($this->sanitize($this->renderStaticBlockMarkup($block, $options), $options));

        if ($this->firstElementMatches($html, 'div', 'wp-block-media-text')) {
            $html = $this->postProcessStaticInnerBlocks($block, $html, $inner);
            $html = $this->applyStaticLayoutAttributes($block, $html);

            return $this->applyStaticWrapperSupportAttributes($block, $html);
        }

        $media = $this->renderMediaTextMedia($block);

        if ($media === '' && $inner === '') {
            return '';
        }

        $classes = ['wp-block-media-text'];
        $mediaPosition = (string) $block->attribute('mediaPosition', 'left');

        if ($mediaPosition === 'right') {
            $classes[] = 'has-media-on-the-right';
        }

        if (! $this->explicitlyFalse($block->attribute('isStackedOnMobile', true))) {
            $classes[] = 'is-stacked-on-mobile';
        }

        $verticalAlignment = $this->safeClassSlug($block->attribute('verticalAlignment'));

        if ($verticalAlignment) {
            $classes[] = 'is-vertically-aligned-'.$verticalAlignment;
        }

        if ($this->truthy($block->attribute('imageFill', false))) {
            $classes[] = 'is-image-fill-element';
        }

        $style = $this->mediaTextGridStyle($block);
        $mediaFigure = '<figure class="wp-block-media-text__media">'.$media.'</figure>';
        $content = '<div class="wp-block-media-text__content">'.$inner.'</div>';

        if ($mediaPosition === 'right') {
            $content = $content.$mediaFigure;
        } else {
            $content = $mediaFigure.$content;
        }

        return $this->sanitize('<div'.$this->fallbackRootAttributes($block, [
            'class' => implode(' ', $classes),
            'style' => $style,
        ]).'>'.$content.'</div>', $options);
    }

    private function renderMediaTextMedia(Block $block): string
    {
        $url = $block->attribute('mediaUrl');

        if (! is_scalar($url) || trim((string) $url) === '') {
            return '';
        }

        $url = trim((string) $url);
        $type = (string) $block->attribute('mediaType', 'image');

        if ($type === 'video') {
            return '<video controls="controls" src="'.e($url).'"></video>';
        }

        if ($type !== 'image') {
            return '';
        }

        $classes = [];
        $mediaId = $block->attribute('mediaId');

        if ((is_int($mediaId) || ctype_digit((string) $mediaId)) && (int) $mediaId > 0) {
            $classes[] = 'wp-image-'.(int) $mediaId;
        }

        $mediaSizeSlug = $this->safeClassSlug($block->attribute('mediaSizeSlug', 'full'));

        if ($classes !== [] && $mediaSizeSlug) {
            $classes[] = 'size-'.$mediaSizeSlug;
        }

        $image = '<img'.$this->renderAttributes([
            'src' => $url,
            'alt' => is_scalar($block->attribute('mediaAlt', '')) ? (string) $block->attribute('mediaAlt', '') : '',
            'class' => implode(' ', $classes),
            'style' => $this->truthy($block->attribute('imageFill', false)) ? $this->mediaTextImageFillStyle($block) : '',
        ]).'>';

        $href = $block->attribute('href');

        if (! is_scalar($href) || trim((string) $href) === '') {
            return $image;
        }

        return '<a'.$this->renderAttributes([
            'class' => implode(' ', $this->safeClassList($block->attribute('linkClass'))),
            'href' => trim((string) $href),
            'target' => $this->safeLinkTarget($block->attribute('linkTarget')),
            'rel' => is_scalar($block->attribute('rel')) ? trim((string) $block->attribute('rel')) : '',
        ]).'>'.$image.'</a>';
    }

    private function mediaTextGridStyle(Block $block): string
    {
        $width = $block->attribute('mediaWidth', 50);

        if (! is_numeric($width) || (float) $width === 50.0) {
            return '';
        }

        $width = max(1, min(100, (float) $width));
        $width = rtrim(rtrim(number_format($width, 2, '.', ''), '0'), '.');
        $columns = (string) $block->attribute('mediaPosition', 'left') === 'right'
            ? 'auto '.$width.'%'
            : $width.'% auto';

        return 'grid-template-columns: '.$columns;
    }

    private function mediaTextImageFillStyle(Block $block): string
    {
        $focalPoint = $block->attribute('focalPoint', []);
        $x = 0.5;
        $y = 0.5;

        if (is_array($focalPoint)) {
            $x = is_numeric($focalPoint['x'] ?? null) ? max(0, min(1, (float) $focalPoint['x'])) : $x;
            $y = is_numeric($focalPoint['y'] ?? null) ? max(0, min(1, (float) $focalPoint['y'])) : $y;
        }

        return 'object-position: '.round($x * 100).'% '.round($y * 100).'%';
    }

    private function renderIcon(Block $block, array $options): string
    {
        $saved = trim($this->sanitize($block->renderableHtml(), $options));
        $iconName = (string) $block->attribute('icon', '');

        if ($iconName === '') {
            return $saved;
        }

        $icon = app(IconRepository::class)->find($iconName);

        if (! $icon) {
            return $saved;
        }

        $svg = $this->transformFirstElement($icon['content'], ['svg'], function (DOMDocument $document, DOMElement $svg) use ($block): void {
            $ariaLabel = (string) $block->attribute('ariaLabel', '');

            if ($ariaLabel === '') {
                $svg->setAttribute('aria-hidden', 'true');
                $svg->setAttribute('focusable', 'false');

                return;
            }

            $svg->setAttribute('role', 'img');
            $svg->setAttribute('aria-label', $ariaLabel);
        });

        return sprintf(
            '<div%s>%s</div>',
            BlockWrapperContext::withBlock($block, fn (): string => BlockWrapperContext::wrapperAttributes(['class' => 'wp-block-icon'])),
            $svg
        );
    }

    private function renderEmbed(Block $block, array $options): string
    {
        $url = trim((string) $block->attribute('url', ''));

        if ($url === '') {
            $url = $this->extractFirstUrl($block->renderableHtml());
        }

        $embedUrl = $this->youtubeEmbedUrl($url);

        if (! $embedUrl) {
            return $this->applyStaticWrapperSupportAttributes($block, trim($this->sanitize($block->renderableHtml(), $options)));
        }

        $classes = [
            'wp-block-embed',
            'is-type-video',
            'is-provider-youtube',
            'wp-block-embed-youtube',
        ];

        if ($this->truthy($block->attribute('responsive', true))) {
            $classes[] = 'wp-embed-aspect-16-9';
            $classes[] = 'wp-has-aspect-ratio';
        }

        $iframe = sprintf(
            '<iframe src="%s" title="YouTube video" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>',
            e($embedUrl)
        );
        $wrapperAttributes = BlockWrapperContext::withBlock($block, fn (): string => BlockWrapperContext::wrapperAttributes(['class' => implode(' ', $classes)]));
        $saved = trim($this->sanitize($block->renderableHtml(), $options));

        if ($saved !== '') {
            $preserved = $this->replaceEmbedWrapperWithIframe($saved, $wrapperAttributes, $iframe);

            if ($preserved !== null) {
                return $preserved;
            }
        }

        return sprintf(
            '<figure%s><div class="wp-block-embed__wrapper">%s</div></figure>',
            $wrapperAttributes,
            $iframe
        );
    }

    private function replaceEmbedWrapperWithIframe(string $html, string $wrapperAttributes, string $iframe): ?string
    {
        if (trim($html) === '' || ! class_exists(DOMDocument::class)) {
            return null;
        }

        $document = $this->loadFragment($html);
        $wrapper = $document->getElementById('__statamic_gutenberg_fragment__');

        if (! $wrapper) {
            return null;
        }

        foreach ($wrapper->childNodes as $child) {
            if (! $child instanceof DOMElement || strtolower($child->tagName) !== 'figure') {
                continue;
            }

            $this->mergeWrapperAttributes($child, $wrapperAttributes);
            $embedWrapper = $this->firstDescendantWithClass($child, 'div', 'wp-block-embed__wrapper');

            if (! $embedWrapper) {
                $embedWrapper = $document->createElement('div');
                $embedWrapper->setAttribute('class', 'wp-block-embed__wrapper');
                $child->insertBefore($embedWrapper, $child->firstChild);
            }

            $this->replaceElementInnerHtml($document, $embedWrapper, $iframe);

            return $this->fragmentHtml($document, $wrapper);
        }

        return null;
    }

    private function mergeWrapperAttributes(DOMElement $element, string $wrapperAttributes): void
    {
        $fragment = $this->firstElementFragment('<figure'.$wrapperAttributes.'></figure>');
        $attributes = is_array($fragment) ? ($fragment['attributes'] ?? []) : [];

        foreach ($attributes as $name => $value) {
            if ($name === 'class') {
                $this->addClasses($element, preg_split('/\s+/', $value) ?: []);
                continue;
            }

            if ($name === 'style') {
                $element->setAttribute('style', $this->mergeStyles($element->getAttribute('style'), $value));
                continue;
            }

            if (! $element->hasAttribute($name) || $element->getAttribute($name) === '') {
                $element->setAttribute($name, $value);
            }
        }
    }

    private function firstDescendantWithClass(DOMElement $element, string $tagName, string $className): ?DOMElement
    {
        foreach ($element->getElementsByTagName($tagName) as $descendant) {
            if ($descendant instanceof DOMElement && $this->elementHasClass($descendant, $className)) {
                return $descendant;
            }
        }

        return null;
    }

    private function extractFirstUrl(string $html): string
    {
        if (preg_match('/https?:\/\/[^\s<"]+/i', $html, $matches)) {
            return $matches[0];
        }

        return '';
    }

    private function youtubeEmbedUrl(string $rawUrl): ?string
    {
        $host = parse_url($rawUrl, PHP_URL_HOST);
        $path = parse_url($rawUrl, PHP_URL_PATH) ?: '';
        $query = [];

        parse_str(parse_url($rawUrl, PHP_URL_QUERY) ?: '', $query);

        $host = strtolower(preg_replace('/^www\./', '', (string) $host));
        $id = '';

        if ($host === 'youtu.be') {
            $id = trim($path, '/');
        } elseif (in_array($host, ['youtube.com', 'youtube-nocookie.com'], true)) {
            if (str_starts_with($path, '/embed/')) {
                $id = explode('/', trim($path, '/'))[1] ?? '';
            } elseif ($path === '/watch') {
                $id = (string) ($query['v'] ?? '');
            } elseif (str_starts_with($path, '/shorts/')) {
                $id = explode('/', trim($path, '/'))[1] ?? '';
            }
        }

        if (! preg_match('/^[A-Za-z0-9_-]{6,}$/', $id)) {
            return null;
        }

        return 'https://www.youtube.com/embed/'.$id;
    }

    private function enableImageLightbox(string $html): string
    {
        return $this->transformFirstElement($html, ['figure'], function (DOMDocument $document, DOMElement $figure): void {
            $this->addClasses($figure, ['wp-lightbox-container']);
            $figure->setAttribute('data-sgb-lightbox', 'true');

            $image = $figure->getElementsByTagName('img')->item(0);

            if (! $image instanceof DOMElement || $this->hasLightboxTrigger($figure)) {
                return;
            }

            $button = $document->createElement('button');
            $button->setAttribute('class', 'lightbox-trigger');
            $button->setAttribute('type', 'button');
            $button->setAttribute('aria-haspopup', 'dialog');
            $button->setAttribute('aria-label', 'Enlarge image');
            $button->setAttribute('data-sgb-lightbox-trigger', 'true');

            $icon = $document->createElement('span');
            $icon->setAttribute('class', 'lightbox-trigger__icon');
            $icon->setAttribute('aria-hidden', 'true');
            $button->appendChild($icon);

            $insertAfter = $image->parentNode instanceof DOMElement && strtolower($image->parentNode->tagName) === 'a'
                ? $image->parentNode
                : $image;
            $insertAfter->parentNode?->insertBefore($button, $insertAfter->nextSibling);
        });
    }

    private function applyDuotone(Block $block, string $html): string
    {
        $selector = Duotone::blockSelector($block->name());
        $style = $block->attribute('style', []);
        $value = is_array($style) ? ($style['color']['duotone'] ?? null) : null;

        if (! $selector || $value === null || trim($html) === '') {
            return $html;
        }

        $idSuffix = Duotone::presetSlug($value) ?: 'block-'.substr(md5($block->name().serialize($value)), 0, 12);
        $className = 'wp-duotone-'.$idSuffix;
        $duotone = Duotone::styleForValue($value, $this->themeJson->settings(), Duotone::scopedSelector($className, $selector), $idSuffix);

        if (! $duotone) {
            return $html;
        }

        $html = $this->addClassesToFirstElement($html, [$className], ['div', 'figure']);

        return ($duotone['svg'] ?? '')
            .'<style data-statamic-gutenberg-duotone>'.($duotone['css'] ?? '').'</style>'
            .$html;
    }

    private function renderConstructedImage(Block $block): string
    {
        $url = $block->attribute('url');
        $alt = $block->attribute('alt', '');
        $classes = ['wp-block-image'];
        $sizeSlug = $this->safeClassSlug($block->attribute('sizeSlug'));

        if ($sizeSlug) {
            $classes[] = 'size-'.$sizeSlug;
        }

        if (! $url) {
            $image = StatamicAssetImages::image($this->imageAssetId($block), 'full', false, [
                'alt' => is_scalar($alt) ? (string) $alt : '',
            ]);
            $image = $this->applyConstructedImageTargetAttributes($image, $this->imageTargetClasses($block, []), $this->imageTargetStyleDeclarations($block));

            return $image === '' ? '' : '<figure'.$this->renderAttributes($this->constructedImageFigureAttributeArray($block, $classes)).'>'.$image.'</figure>';
        }

        $imageClasses = $this->imageTargetClasses($block, []);
        $id = $block->attribute('id');

        if ((is_int($id) || (is_scalar($id) && ctype_digit((string) $id))) && (int) $id > 0) {
            $imageClasses[] = 'wp-image-'.(int) $id;
        }

        $imageStyles = array_merge($this->imageTargetStyleDeclarations($block), array_filter([
            ($aspectRatio = $this->safeStyleValue($block->attribute('aspectRatio'))) ? 'aspect-ratio: '.$aspectRatio : null,
            ($scale = $this->safeImageObjectFit($block->attribute('scale'))) ? 'object-fit: '.$scale : null,
            ($position = $this->safeFocalPointStyle($block->attribute('focalPoint'))) ? 'object-position: '.$position : null,
        ]));
        $isDecorative = $this->truthy($block->attribute('isDecorative', false));
        $altValue = $isDecorative ? '' : (is_scalar($alt) ? (string) $alt : '');
        $image = '<img alt="'.e($altValue).'"'.$this->renderAttributes([
            'src' => (string) $url,
            'class' => implode(' ', $imageClasses),
            'width' => $this->safeImageDimension($block->attribute('width')),
            'height' => $this->safeImageDimension($block->attribute('height')),
            'title' => is_scalar($block->attribute('title')) ? trim((string) $block->attribute('title')) : '',
            'role' => $isDecorative ? 'presentation' : '',
            'style' => implode('; ', $imageStyles),
        ]).'>';
        $href = $block->attribute('href');

        if (is_scalar($href) && trim((string) $href) !== '') {
            $image = '<a'.$this->renderAttributes([
                'class' => implode(' ', $this->safeClassList($block->attribute('linkClass'))),
                'href' => trim((string) $href),
                'target' => $this->safeLinkTarget($block->attribute('linkTarget')),
                'rel' => is_scalar($block->attribute('rel')) ? trim((string) $block->attribute('rel')) : '',
            ]).'>'.$image.'</a>';
        }

        $captionValue = $block->attribute('caption', '');
        $caption = is_scalar($captionValue) ? trim((string) $captionValue) : '';

        return '<figure'.$this->renderAttributes($this->constructedImageFigureAttributeArray($block, $classes)).'>'.$image.($caption !== '' ? '<figcaption class="wp-element-caption">'.$caption.'</figcaption>' : '').'</figure>';
    }

    private function constructedImageFigureAttributeArray(Block $block, array $classes): array
    {
        $align = $this->safeClassSlug($block->attribute('align'));

        if ($align) {
            $classes[] = 'align'.$align;
        }

        $classes = array_merge($classes, $this->safeClassList($block->attribute('className')));

        if ($this->imageHasCustomBorder($block)) {
            $classes[] = 'has-custom-border';
        }

        $attributes = [
            'class' => implode(' ', array_values(array_unique(array_filter($classes)))),
        ];
        $anchor = $this->safeAnchor($block->attribute('anchor'));

        if ($anchor !== '') {
            $attributes['id'] = $anchor;
        }

        $style = $block->attribute('style', []);
        $margin = is_array($style) ? $this->safeSpacingDeclarations('margin', $style['spacing']['margin'] ?? []) : [];

        if ($margin) {
            $attributes['style'] = implode('; ', $margin);
        }

        return $attributes;
    }

    private function imageHasCustomBorder(Block $block): bool
    {
        $style = $block->attribute('style', []);
        $border = is_array($style) && is_array($style['border'] ?? null) ? $style['border'] : [];

        return $this->searchBorderColorClasses($block) !== []
            || $this->searchBorderDeclarations($border, 'width') !== []
            || $this->searchBorderDeclarations($border, 'style') !== []
            || $this->searchBorderRadiusDeclarations($border['radius'] ?? null) !== [];
    }

    private function imageTargetClasses(Block $block, array $classes): array
    {
        return array_merge($classes, $this->searchBorderColorClasses($block));
    }

    private function imageTargetStyleDeclarations(Block $block): array
    {
        $style = $block->attribute('style', []);
        $border = is_array($style) && is_array($style['border'] ?? null) ? $style['border'] : [];
        $declarations = [];

        foreach (['width', 'color', 'style'] as $property) {
            $declarations = array_merge($declarations, $this->searchBorderDeclarations($border, $property));
        }

        $declarations = array_merge($declarations, $this->searchBorderRadiusDeclarations($border['radius'] ?? null));

        if ($shadow = $this->safeStyleValue(is_array($style) ? ($style['shadow'] ?? null) : null)) {
            $declarations[] = 'box-shadow: '.$shadow;
        }

        return $declarations;
    }

    private function applyConstructedImageTargetAttributes(string $html, array $classes, array $styles): string
    {
        if ($html === '' || ($classes === [] && $styles === [])) {
            return $html;
        }

        return $this->transformFirstElement($html, ['img'], function (DOMDocument $document, DOMElement $element) use ($classes, $styles): void {
            if ($classes) {
                $this->addClasses($element, $classes);
            }

            if ($styles) {
                $element->setAttribute('style', $this->mergeStyles($element->getAttribute('style'), implode('; ', $styles)));
            }
        });
    }

    private function safeImageDimension(mixed $value): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }

        $value = trim((string) $value);

        return preg_match('/^[1-9][0-9]*$/', $value) ? $value : '';
    }

    private function safeImageObjectFit(mixed $value): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }

        $value = trim((string) $value);

        return in_array($value, ['cover', 'contain', 'fill', 'none', 'scale-down'], true) ? $value : '';
    }

    private function safeFocalPointStyle(mixed $value): string
    {
        if (! is_array($value)) {
            return '';
        }

        $x = $value['x'] ?? null;
        $y = $value['y'] ?? null;

        if (! is_numeric($x) || ! is_numeric($y)) {
            return '';
        }

        $x = max(0, min(1, (float) $x));
        $y = max(0, min(1, (float) $y));

        return round($x * 100, 4).'% '.round($y * 100, 4).'%';
    }

    private function imageAssetId(Block $block): mixed
    {
        foreach (['statamicId', 'mediaId', 'imageId', 'id'] as $attribute) {
            $value = $block->attribute($attribute);

            if (is_scalar($value) && trim((string) $value) !== '') {
                return $value;
            }
        }

        return null;
    }

    private function sanitize(string $html, array $options): string
    {
        if (! ($options['sanitize_html'] ?? true)) {
            return $html;
        }

        return $this->sanitizer->sanitize($html);
    }

    private function firstElementFragment(string $html): ?array
    {
        if (trim($html) === '' || ! class_exists(DOMDocument::class)) {
            return null;
        }

        $document = $this->loadFragment($html);
        $wrapper = $document->getElementById('__statamic_gutenberg_fragment__');

        if (! $wrapper) {
            return null;
        }

        foreach ($wrapper->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $attributes = [];

            foreach ($child->attributes as $attribute) {
                $attributes[$attribute->name] = $attribute->value;
            }

            return [
                'tag' => strtolower($child->tagName),
                'attributes' => $attributes,
                'html' => $this->innerHtml($document, $child),
            ];
        }

        return null;
    }

    private function elementAttributes(DOMElement $element): array
    {
        $attributes = [];

        foreach ($element->attributes as $attribute) {
            $attributes[$attribute->name] = $attribute->value;
        }

        return $attributes;
    }

    private function replaceElementAttributes(DOMElement $element, array $attributes): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $element->removeAttribute($attribute->name);
        }

        foreach ($attributes as $name => $value) {
            if ($value === null || $value === false || $value === '') {
                continue;
            }

            if ($value === true) {
                $value = $name;
            }

            if (is_array($value) || is_object($value)) {
                continue;
            }

            $safeName = preg_replace('/[^a-zA-Z0-9_:\-]/', '', (string) $name);

            if ($safeName === '') {
                continue;
            }

            $element->setAttribute($safeName, (string) $value);
        }
    }

    private function fragmentHasClass(array $fragment, string $className): bool
    {
        return in_array($className, preg_split('/\s+/', trim((string) (($fragment['attributes'] ?? [])['class'] ?? ''))) ?: [], true);
    }

    private function firstElementMatches(string $html, string $tagName, string $className): bool
    {
        $fragment = $this->firstElementFragment($html);

        if (! $fragment || ($fragment['tag'] ?? '') !== $tagName) {
            return false;
        }

        return in_array($className, preg_split('/\s+/', trim((string) (($fragment['attributes'] ?? [])['class'] ?? ''))) ?: [], true);
    }

    private function firstElementHasClass(string $html, string $className): bool
    {
        $fragment = $this->firstElementFragment($html);

        if (! $fragment) {
            return false;
        }

        return in_array($className, preg_split('/\s+/', trim((string) (($fragment['attributes'] ?? [])['class'] ?? ''))) ?: [], true);
    }

    private function addClassesToFirstElement(string $html, array $classes, array $allowedTags): string
    {
        return $this->transformFirstElement($html, $allowedTags, function (DOMDocument $document, DOMElement $element) use ($classes): void {
            $this->addClasses($element, $classes);
        });
    }

    private function transformFirstElement(string $html, array $allowedTags, callable $callback): string
    {
        if (trim($html) === '' || ! class_exists(DOMDocument::class)) {
            return $html;
        }

        $document = $this->loadFragment($html);
        $wrapper = $document->getElementById('__statamic_gutenberg_fragment__');

        if (! $wrapper) {
            return $html;
        }

        foreach ($wrapper->childNodes as $child) {
            if (! $child instanceof DOMElement || ! in_array(strtolower($child->tagName), $allowedTags, true)) {
                continue;
            }

            $callback($document, $child);

            return $this->fragmentHtml($document, $wrapper);
        }

        return $html;
    }

    private function loadFragment(string $html): DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML(
            '<div id="__statamic_gutenberg_fragment__">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $document;
    }

    private function fragmentHtml(DOMDocument $document, DOMElement $wrapper): string
    {
        $html = '';

        foreach ($wrapper->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }

        return $html;
    }

    private function innerHtml(DOMDocument $document, DOMElement $element): string
    {
        $html = '';

        foreach ($element->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }

        return $html;
    }

    private function renderAttributes(array $attributes): string
    {
        $html = '';

        foreach ($attributes as $name => $value) {
            if ($value === '') {
                continue;
            }

            $html .= sprintf(' %s="%s"', $name, e($value));
        }

        return $html;
    }

    private function addClasses(DOMElement $element, array $classes): void
    {
        $element->setAttribute('class', $this->mergeClasses($element->getAttribute('class'), $classes));
    }

    private function elementHasClass(DOMElement $element, string $class): bool
    {
        return in_array($class, preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [], true);
    }

    private function mergeClasses(string $existing, array $classes): string
    {
        $classNames = preg_split('/\s+/', trim($existing)) ?: [];
        $classNames = array_merge($classNames, $classes);
        $classNames = array_values(array_unique(array_filter($classNames)));

        return implode(' ', $classNames);
    }

    private function mergeStyles(string $existing, string $addition): string
    {
        $declarations = [];

        foreach ([$existing, $addition] as $style) {
            foreach (explode(';', $style) as $declaration) {
                $declaration = trim($declaration);

                if ($declaration === '' || ! str_contains($declaration, ':')) {
                    continue;
                }

                [$property, $value] = array_map('trim', explode(':', $declaration, 2));

                if ($property === '' || $value === '') {
                    continue;
                }

                $declarations[strtolower($property)] = "{$property}: {$value}";
            }
        }

        return implode('; ', array_values($declarations));
    }

    private function hasLightboxTrigger(DOMElement $figure): bool
    {
        foreach ($figure->getElementsByTagName('button') as $button) {
            if ($button instanceof DOMElement && in_array('lightbox-trigger', preg_split('/\s+/', $button->getAttribute('class')) ?: [], true)) {
                return true;
            }
        }

        return false;
    }

    private function safeAnchor(mixed $value): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }

        $value = trim((string) $value);

        return preg_match('/^[A-Za-z][A-Za-z0-9_:\-\.]*$/', $value) ? $value : '';
    }

    private function safeClassSlug(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return preg_match('/^[A-Za-z0-9_-]+$/', $value) ? $value : null;
    }

    private function safeClassList(mixed $value): array
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return [];
        }

        return array_values(array_filter(
            preg_split('/\s+/', trim((string) $value)) ?: [],
            fn (string $class): bool => (bool) preg_match('/^[A-Za-z0-9_-]+$/', $class)
        ));
    }

    private function safeSpacingDeclarations(string $property, mixed $values): array
    {
        if (is_string($values) || is_numeric($values)) {
            $value = $this->safeStyleValue($values);

            return $value ? ["{$property}: {$value}"] : [];
        }

        if (! is_array($values)) {
            return [];
        }

        $declarations = [];

        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $value = $this->safeStyleValue($values[$side] ?? null);

            if ($value) {
                $declarations[] = "{$property}-{$side}: {$value}";
            }
        }

        return $declarations;
    }

    private function safeLinkTarget(mixed $value): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }

        $value = trim((string) $value);

        return in_array($value, ['_blank', '_self', '_parent', '_top'], true) ? $value : '';
    }

    private function fileNameFromUrl(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $basename = basename($path);

        return $basename !== '' && $basename !== '.' ? $basename : 'Download file';
    }

    private function renderVideoTracks(mixed $tracks): string
    {
        if (! is_array($tracks)) {
            return '';
        }

        $html = '';

        foreach ($tracks as $track) {
            if (! is_array($track)) {
                continue;
            }

            $src = $track['src'] ?? null;

            if (! is_scalar($src) || trim((string) $src) === '') {
                continue;
            }

            $kind = $this->safeTrackKind($track['kind'] ?? null);

            $html .= '<track'.$this->renderAttributes([
                'src' => trim((string) $src),
                'kind' => $kind,
                'srclang' => $this->safeLanguageTag($track['srcLang'] ?? $track['srclang'] ?? null),
                'label' => is_scalar($track['label'] ?? null) ? trim((string) $track['label']) : '',
                'default' => $this->truthy($track['default'] ?? false) ? 'default' : '',
            ]).'>';
        }

        return $html;
    }

    private function safeTrackKind(mixed $value): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }

        $value = strtolower(trim((string) $value));

        return in_array($value, ['subtitles', 'captions', 'descriptions', 'chapters', 'metadata'], true) ? $value : '';
    }

    private function safeLanguageTag(mixed $value): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }

        $value = trim((string) $value);

        return preg_match('/^[A-Za-z]{2,8}(?:-[A-Za-z0-9]{1,8})*$/', $value) ? $value : '';
    }

    private function truthy(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }

    private function explicitlyFalse(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value === false;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value === 0.0;
        }

        if (! is_string($value)) {
            return false;
        }

        return in_array(strtolower(trim($value)), ['0', 'false', 'no', 'off'], true);
    }

    private function safeMediaPreload(mixed $value): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }

        $value = strtolower(trim((string) $value));

        return in_array($value, ['auto', 'metadata', 'none'], true) ? $value : '';
    }
}
