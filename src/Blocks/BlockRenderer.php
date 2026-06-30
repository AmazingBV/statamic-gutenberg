<?php

namespace Amazingbv\StatamicGutenberg\Blocks;

use Amazingbv\StatamicGutenberg\Icons\IconRepository;
use Amazingbv\StatamicGutenberg\Patterns\PatternRepository;
use Amazingbv\StatamicGutenberg\Support\Duotone;
use Amazingbv\StatamicGutenberg\Support\BlockWrapperContext;
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
    public function __construct(
        private BlockParser $parser,
        private BlockRegistry $registry,
        private Sanitizer $sanitizer,
        private PatternRepository $patterns,
        private ThemeJson $themeJson,
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
            return (string) $definition($block, $inner, $this);
        }

        if (is_array($definition) && isset($definition['view'])) {
            return (string) BlockWrapperContext::withBlock($block, fn (): string => view($definition['view'], [
                'block' => $block,
                'attrs' => $block->attributes(),
                'inner' => new HtmlString($inner),
            ])->render());
        }

        if (is_array($definition) && isset($definition['custom_block'])) {
            return $this->renderCustomBlock($block, $inner, $definition['custom_block'], $options);
        }

        return $this->renderCoreBlock($block, $inner, $options);
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
                $result = include $render;
                $output = (string) ob_get_clean();

                if (is_string($result) || $result instanceof Stringable) {
                    return (string) $result;
                }

                return $output;
            });
        }

        return trim($this->sanitize($block->renderableHtml(), $options));
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
            'core/heading' => $this->renderHeading($block, $options),
            'core/icon' => $this->renderIcon($block, $options),
            'core/image' => $this->renderImage($block, $options),
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
            '<form role="search" method="get"%s><label class="wp-block-search__label%s" for="%s">%s</label><div%s><input class="wp-block-search__input" id="%s" type="search" name="q" value="" placeholder="%s">%s%s</div></form>',
            $this->fallbackRootAttributes($block, [
                'class' => implode(' ', $classes),
                'action' => $this->siteUrl('/search'),
            ]),
            $showLabel ? '' : ' sgb-screen-reader-text',
            e($inputId),
            e($label),
            $this->renderAttributes([
                'class' => 'wp-block-search__inside-wrapper',
                'style' => $this->searchInsideWrapperStyle($block),
            ]),
            e($inputId),
            e($placeholder),
            $this->searchHiddenInputs($block),
            $hasButton ? $this->searchButton($button, $buttonUseIcon) : '',
        );
    }

    private function searchButtonPosition(string $buttonPosition): string
    {
        return in_array($buttonPosition, ['button-inside', 'button-outside', 'button-only', 'no-button'], true)
            ? $buttonPosition
            : 'button-outside';
    }

    private function searchInsideWrapperStyle(Block $block): string
    {
        $width = $block->attribute('width');

        if (! is_string($width) && ! is_numeric($width)) {
            return '';
        }

        $width = trim((string) $width);

        if ($width === '' || ! preg_match('/^\d+(?:\.\d+)?$/', $width)) {
            return '';
        }

        $unit = (string) $block->attribute('widthUnit', '%');
        $unit = in_array($unit, ['%', 'px', 'em', 'rem', 'vw', 'vh'], true) ? $unit : '%';

        return 'width: '.$width.$unit;
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

    private function searchButton(string $button, bool $useIcon): string
    {
        $content = $useIcon
            ? $this->searchIcon().'<span class="sgb-screen-reader-text">'.e($button).'</span>'
            : e($button);

        return sprintf(
            '<button class="wp-block-search__button wp-element-button" type="submit"%s>%s</button>',
            $useIcon ? $this->renderAttributes(['aria-label' => $button]) : '',
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

    private function fallbackRootAttributes(Block $block, array $attributes): string
    {
        return BlockWrapperContext::withBlock(
            $block,
            fn (): string => BlockWrapperContext::wrapperAttributes($attributes)
        );
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
        $fragment = $this->firstElementFragment($this->sanitize($block->renderableHtml(), $options));

        if (! $fragment) {
            $attributes = $this->applyLayoutAttributes($block, array_merge(['class' => $baseClass], $extraAttributes), $baseClass);

            return sprintf(
                '<%s%s>%s</%s>',
                $fallbackTag,
                $this->renderAttributes($attributes),
                $inner,
                $fallbackTag
            );
        }

        $tag = in_array($fragment['tag'], $allowedTags, true) ? $fragment['tag'] : $fallbackTag;
        $attributes = array_merge($fragment['attributes'], $extraAttributes);
        $attributes = $this->applyLayoutAttributes($block, $attributes, $baseClass);

        return sprintf(
            '<%s%s>%s</%s>',
            $tag,
            $this->renderAttributes($attributes),
            $inner !== '' ? $inner : $fragment['html'],
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

        if ($html === '') {
            $content = trim((string) $block->attribute('content', ''));

            if ($content === '') {
                return '';
            }

            $level = max(1, min(6, (int) $block->attribute('level', 2)));
            $html = sprintf('<h%d>%s</h%d>', $level, $content, $level);
        }

        $classes = ['wp-block-heading'];

        if ($this->truthy($block->attribute('fitText', false))) {
            $classes[] = 'has-fit-text';
        }

        return $this->addClassesToFirstElement(
            $this->sanitize($html, $options),
            $classes,
            ['h1', 'h2', 'h3', 'h4', 'h5', 'h6']
        );
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
                $html = sprintf('<figure class="wp-block-video"><video src="%s"></video></figure>', e((string) $src));
            }
        }

        if ($html === '') {
            return '';
        }

        $html = $this->sanitize($html, $options);

        return $this->transformFirstElement($html, ['figure', 'video'], function (DOMDocument $document, DOMElement $element) use ($block): void {
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
    }

    private function renderAudio(Block $block, array $options): string
    {
        $html = trim($block->renderableHtml());

        if ($html === '') {
            $src = $block->attribute('src');

            if (is_scalar($src) && trim((string) $src) !== '') {
                $caption = trim((string) $block->attribute('caption', ''));
                $html = sprintf(
                    '<figure class="wp-block-audio"><audio%s></audio>%s</figure>',
                    $this->renderAttributes([
                        'controls' => 'controls',
                        'src' => (string) $src,
                        'autoplay' => $this->truthy($block->attribute('autoplay', false)) ? 'autoplay' : '',
                        'loop' => $this->truthy($block->attribute('loop', false)) ? 'loop' : '',
                        'preload' => $this->safeAudioPreload($block->attribute('preload')),
                    ]),
                    $caption !== '' ? '<figcaption class="wp-element-caption">'.e($caption).'</figcaption>' : ''
                );
            }
        }

        if ($html === '') {
            return '';
        }

        $html = $this->sanitize($html, $options);

        return $this->transformFirstElement($html, ['figure', 'audio'], function (DOMDocument $document, DOMElement $element): void {
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
    }

    private function renderSpacer(Block $block, array $options): string
    {
        $html = trim($block->renderableHtml());

        if ($html !== '') {
            return $this->addClassesToFirstElement($this->sanitize($html, $options), ['wp-block-spacer'], ['div']);
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
            return $this->addClassesToFirstElement($this->sanitize($html, $options), ['wp-block-separator'], ['hr', 'div']);
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
            return trim($this->sanitize($block->renderableHtml(), $options));
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

        if (! $url) {
            $image = StatamicAssetImages::image($this->imageAssetId($block), 'full', false, [
                'alt' => is_scalar($alt) ? (string) $alt : '',
            ]);

            return $image === '' ? '' : '<figure class="wp-block-image">'.$image.'</figure>';
        }

        return sprintf(
            '<figure class="wp-block-image"><img src="%s" alt="%s"></figure>',
            e($url),
            e($alt)
        );
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
        $styles = array_filter([
            trim(rtrim($existing, ';')),
            trim(rtrim($addition, ';')),
        ]);

        return implode('; ', $styles);
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

    private function safeAudioPreload(mixed $value): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }

        $value = strtolower(trim((string) $value));

        return in_array($value, ['auto', 'metadata', 'none'], true) ? $value : '';
    }
}
