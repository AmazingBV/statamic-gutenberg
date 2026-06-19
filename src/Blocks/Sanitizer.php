<?php

namespace Amazingbv\StatamicGutenberg\Blocks;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;

class Sanitizer
{
    private const SAFE_STYLE_PROPERTIES = [
        'align-items',
        'aspect-ratio',
        'background',
        'background-color',
        'background-image',
        'background-position',
        'background-repeat',
        'background-size',
        'border',
        'border-bottom',
        'border-bottom-color',
        'border-bottom-left-radius',
        'border-bottom-right-radius',
        'border-bottom-style',
        'border-bottom-width',
        'border-color',
        'border-left',
        'border-left-color',
        'border-left-style',
        'border-left-width',
        'border-radius',
        'border-right',
        'border-right-color',
        'border-right-style',
        'border-right-width',
        'border-style',
        'border-top',
        'border-top-color',
        'border-top-left-radius',
        'border-top-right-radius',
        'border-top-style',
        'border-top-width',
        'border-width',
        'box-shadow',
        'color',
        'display',
        'fill',
        'flex-basis',
        'flex-grow',
        'flex-shrink',
        'flex-wrap',
        'font-family',
        'font-size',
        'font-style',
        'font-weight',
        'gap',
        'grid-auto-columns',
        'grid-auto-flow',
        'grid-auto-rows',
        'grid-column',
        'grid-column-end',
        'grid-column-start',
        'grid-row',
        'grid-row-end',
        'grid-row-start',
        'grid-template-columns',
        'grid-template-rows',
        'height',
        'justify-content',
        'letter-spacing',
        'line-height',
        'margin',
        'margin-bottom',
        'margin-left',
        'margin-right',
        'margin-top',
        'max-height',
        'max-width',
        'min-height',
        'min-width',
        'object-fit',
        'object-position',
        'padding',
        'padding-bottom',
        'padding-left',
        'padding-right',
        'padding-top',
        'place-content',
        'place-items',
        'place-self',
        'row-gap',
        'text-align',
        'text-decoration',
        'text-transform',
        'width',
        'writing-mode',
    ];

    private const REMOVED_TAGS = [
        'script',
        'style',
        'iframe',
        'object',
        'embed',
        'link',
        'meta',
    ];

    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        if (! class_exists(DOMDocument::class)) {
            return strip_tags($html);
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML(
            '<div id="__statamic_gutenberg_fragment__">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        $this->cleanNode($document);

        $wrapper = $document->getElementById('__statamic_gutenberg_fragment__');
        $output = '';

        if ($wrapper) {
            foreach ($wrapper->childNodes as $child) {
                $output .= $document->saveHTML($child);
            }
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $output;
    }

    private function cleanNode(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMComment) {
                $child->parentNode?->removeChild($child);

                continue;
            }

            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);

                if (in_array($tag, self::REMOVED_TAGS, true)) {
                    $child->parentNode?->removeChild($child);

                    continue;
                }

                $this->cleanAttributes($child);
            }

            $this->cleanNode($child);
        }
    }

    private function cleanAttributes(DOMElement $element): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            $value = trim($attribute->value);

            if (str_starts_with($name, 'on')) {
                $element->removeAttributeNode($attribute);

                continue;
            }

            if ($name === 'style') {
                $style = $this->sanitizeStyle($value);

                if ($style === '') {
                    $element->removeAttributeNode($attribute);

                    continue;
                }

                $element->setAttribute('style', $style);

                continue;
            }

            if (in_array($name, ['href', 'src', 'xlink:href', 'srcset'], true) && $this->isDangerousUrl($value)) {
                $element->removeAttributeNode($attribute);
            }
        }
    }

    private function sanitizeStyle(string $style): string
    {
        $declarations = [];

        foreach (explode(';', $style) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }

            [$property, $value] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower(preg_replace('/\s+/', '', $property));

            if (! in_array($property, self::SAFE_STYLE_PROPERTIES, true)) {
                continue;
            }

            if (! $this->isSafeStyleValue($value)) {
                continue;
            }

            $declarations[] = "{$property}: {$value}";
        }

        return implode('; ', $declarations);
    }

    private function isSafeStyleValue(string $value): bool
    {
        $normalized = strtolower(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $normalized = preg_replace('/\/\*.*?\*\//s', '', $normalized);

        return ! preg_match('/(?:url\s*\(|expression\s*\(|javascript\s*:|vbscript\s*:|data\s*:|@import|-moz-binding|behavior\s*:|[<>\\\\])/', $normalized);
    }

    private function isDangerousUrl(string $value): bool
    {
        $normalized = strtolower(preg_replace('/\s+/', '', html_entity_decode($value)));

        return str_starts_with($normalized, 'javascript:')
            || str_starts_with($normalized, 'vbscript:')
            || str_starts_with($normalized, 'blob:')
            || (str_starts_with($normalized, 'data:') && ! str_starts_with($normalized, 'data:image/'));
    }
}
