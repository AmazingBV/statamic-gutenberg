<?php

namespace Amazingbv\StatamicGutenberg\Blocks;

use DOMDocument;
use DOMElement;
use Illuminate\Support\HtmlString;

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
            'core/heading' => $this->renderHeading($block, $options),
            'core/image' => $this->renderImage($block, $options),
            default => $this->sanitize($block->renderableHtml(), $options),
        };
    }

    private function renderWrapperBlock(
        Block $block,
        string $inner,
        array $options,
        string $fallbackTag,
        string $baseClass,
        array $allowedTags = ['div']
    ): string {
        $fragment = $this->firstElementFragment($this->sanitize($block->renderableHtml(), $options));

        if (! $fragment) {
            return sprintf(
                '<%s class="%s">%s</%s>',
                $fallbackTag,
                e($baseClass),
                $inner,
                $fallbackTag
            );
        }

        $tag = in_array($fragment['tag'], $allowedTags, true) ? $fragment['tag'] : $fallbackTag;
        $attributes = $fragment['attributes'];
        $attributes['class'] = $this->mergeClasses($attributes['class'] ?? '', [$baseClass]);

        return sprintf(
            '<%s%s>%s</%s>',
            $tag,
            $this->renderAttributes($attributes),
            $inner !== '' ? $inner : $fragment['html'],
            $tag
        );
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
