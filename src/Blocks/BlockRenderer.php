<?php

namespace Amazingbv\StatamicGutenberg\Blocks;

use Amazingbv\StatamicGutenberg\Icons\IconRepository;
use DOMDocument;
use DOMElement;
use Illuminate\Support\HtmlString;
use Statamic\Facades\Entry;
use Throwable;

class BlockRenderer
{
    public function __construct(
        private BlockParser $parser,
        private BlockRegistry $registry,
        private Sanitizer $sanitizer,
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

        if (! $this->registry->isAllowed($block->name(), is_array($allowed) ? $allowed : null)) {
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
            return view($definition['view'], [
                'block' => $block,
                'attrs' => $block->attributes(),
                'inner' => new HtmlString($inner),
            ])->render();
        }

        return $this->renderCoreBlock($block, $inner, $options);
    }

    private function renderCoreBlock(Block $block, string $inner, array $options): string
    {
        return match ($block->name()) {
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
            'core/embed' => $this->renderEmbed($block, $options),
            'core/heading' => $this->renderHeading($block, $options),
            'core/icon' => $this->renderIcon($block, $options),
            'core/image' => $this->renderImage($block, $options),
            default => $this->renderStaticOrFallbackCoreBlock($block, $inner, $options),
        };
    }

    private function renderStaticOrFallbackCoreBlock(Block $block, string $inner, array $options): string
    {
        $html = trim($this->sanitize($block->renderableHtml(), $options));

        if ($html !== '') {
            return $html;
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
            'core/loginout' => $this->renderLoginoutFallback(),
            'core/navigation-overlay-close' => '<button class="wp-block-navigation__responsive-container-close" type="button">Close</button>',
            default => $this->renderCoreFallbackNotice($block, $inner),
        };
    }

    private function renderSearchFallback(Block $block): string
    {
        $label = trim((string) $block->attribute('label', 'Search')) ?: 'Search';
        $placeholder = (string) $block->attribute('placeholder', '');
        $button = trim((string) $block->attribute('buttonText', 'Search')) ?: 'Search';
        $showLabel = $this->truthy($block->attribute('showLabel', true));
        $buttonPosition = (string) $block->attribute('buttonPosition', 'button-outside');
        $classes = ['wp-block-search', 'sgb-core-fallback-search'];

        if ($buttonPosition !== '') {
            $classes[] = 'wp-block-search__button-'.$buttonPosition;
        }

        return sprintf(
            '<form role="search" method="get"%s><label class="wp-block-search__label%s" for="sgb-search-input">%s</label><div class="wp-block-search__inside-wrapper"><input class="wp-block-search__input" id="sgb-search-input" type="search" name="q" value="" placeholder="%s"><button class="wp-block-search__button" type="submit">%s</button></div></form>',
            $this->renderAttributes([
                'class' => implode(' ', $classes),
                'action' => $this->siteUrl('/search'),
            ]),
            $showLabel ? '' : ' sgb-screen-reader-text',
            e($label),
            e($placeholder),
            e($button),
        );
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

        return sprintf('<%s class="wp-block-site-title">%s</%s>', $tag, $content, $tag);
    }

    private function renderSiteTaglineFallback(Block $block): string
    {
        $level = max(0, min(6, (int) $block->attribute('level', 0)));
        $tag = $level === 0 ? 'p' : 'h'.$level;
        $tagline = trim((string) config('statamic.system.tagline', '')) ?: trim((string) config('app.description', ''));
        $tagline = $tagline !== '' ? $tagline : 'Site tagline';

        return sprintf('<%s class="wp-block-site-tagline">%s</%s>', $tag, e($tagline), $tag);
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

        return '<ul class="wp-block-latest-posts__list wp-block-latest-posts">'.$items.'</ul>';
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

        return sprintf('<ul class="%s">%s</ul>', $class, $items);
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
            '<table class="wp-block-calendar wp-calendar-table"><caption>%s</caption><thead><tr>%s</tr></thead><tbody>%s</tbody></table>',
            e($caption),
            $head,
            $rows
        );
    }

    private function renderReadMoreFallback(Block $block): string
    {
        $content = trim((string) $block->attribute('content', 'Read more')) ?: 'Read more';

        return sprintf(
            '<a class="wp-block-read-more" href="%s"%s>%s</a>',
            e($this->currentUrl()),
            $this->renderAttributes(['target' => (string) $block->attribute('linkTarget', '_self')]),
            e($content)
        );
    }

    private function renderLoginoutFallback(): string
    {
        return sprintf('<a class="wp-block-loginout" href="%s">Log in</a>', e($this->siteUrl('/login')));
    }

    private function renderCoreFallbackNotice(Block $block, string $inner = ''): string
    {
        $class = $this->coreBlockClass($block->name()).' sgb-core-fallback';

        if ($inner !== '') {
            return sprintf(
                '<div%s>%s</div>',
                $this->renderAttributes([
                    'class' => $class,
                    'data-sgb-core-fallback' => $block->name(),
                ]),
                $inner
            );
        }

        return sprintf(
            '<div%s><strong>%s</strong><span>%s</span></div>',
            $this->renderAttributes([
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
        $styles = [];

        if ($baseClass === 'wp-block-group' && is_array($layout)) {
            $type = (string) ($layout['type'] ?? '');

            if ($type === 'flex') {
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
                $minimumColumnWidth = trim((string) ($layout['minimumColumnWidth'] ?? ''));

                if ($columnCount > 0) {
                    $styles[] = 'grid-template-columns: repeat('.min(12, $columnCount).', minmax(0, 1fr))';
                } elseif ($minimumColumnWidth !== '') {
                    $styles[] = 'grid-template-columns: repeat(auto-fit, minmax('.$minimumColumnWidth.', 1fr))';
                }
            }
        }

        $attributes['class'] = $this->mergeClasses($attributes['class'] ?? '', $classes);
        $attributes['style'] = $this->mergeStyles($attributes['style'] ?? '', implode('; ', $styles));

        return $attributes;
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
            return $this->enableImageLightbox($html);
        }

        return $html;
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
            $this->renderAttributes(['class' => 'wp-block-icon']),
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

        return sprintf(
            '<figure%s><div class="wp-block-embed__wrapper"><iframe src="%s" title="YouTube video" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div></figure>',
            $this->renderAttributes(['class' => implode(' ', $classes)]),
            e($embedUrl)
        );
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

    private function renderConstructedImage(Block $block): string
    {
        $url = $block->attribute('url');

        if (! $url) {
            return '';
        }

        $alt = $block->attribute('alt', '');

        return sprintf(
            '<figure class="wp-block-image"><img src="%s" alt="%s"></figure>',
            e($url),
            e($alt)
        );
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

    private function truthy(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }
}
