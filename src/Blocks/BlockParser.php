<?php

namespace Amazingbv\StatamicGutenberg\Blocks;

class BlockParser
{
    public function parse(?string $html): array
    {
        $html ??= '';

        if ($html === '') {
            return [];
        }

        $root = new Block('__root__');
        $stack = [&$root];
        $offset = 0;

        preg_match_all(
            '/<!--\s*(\/)?wp:([a-z0-9_\/-]+)(?:\s+(\{.*?\}))?\s*(\/)?\s*-->/is',
            $html,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );

        foreach ($matches as $match) {
            $token = $match[0][0];
            $start = $match[0][1];
            $before = substr($html, $offset, $start - $offset);

            $this->appendHtml($stack[array_key_last($stack)], $before);

            $isClosing = ($match[1][0] ?? '') === '/';
            $name = $this->normalizeName($match[2][0]);
            $attributes = $this->decodeAttributes($match[3][0] ?? null);
            $selfClosing = ($match[4][0] ?? '') === '/';

            if ($isClosing) {
                $current = $stack[array_key_last($stack)];

                if ($current->name() === $name && count($stack) > 1) {
                    $this->setRawClosing($current, $token);
                    array_pop($stack);
                } else {
                    $this->appendHtml($current, $token);
                }
            } else {
                $block = new Block(
                    name: $name,
                    attributes: $attributes,
                    rawOpening: $token,
                    selfClosing: $selfClosing,
                );

                $stack[array_key_last($stack)]->addInnerBlock($block);

                if (! $selfClosing) {
                    $stack[] = $block;
                }
            }

            $offset = $start + strlen($token);
        }

        $this->appendHtml($stack[array_key_last($stack)], substr($html, $offset));

        return $root->innerBlocks();
    }

    public function serialize(array $blocks): string
    {
        return collect($blocks)
            ->map(fn (Block $block) => $block->serialize())
            ->implode('');
    }

    private function appendHtml(Block $parent, string $html): void
    {
        if ($html === '') {
            return;
        }

        if ($parent->name() === '__root__') {
            $parent->addInnerBlock(Block::freeform($html));

            return;
        }

        $parent->addHtml($html);
    }

    private function normalizeName(string $name): string
    {
        $name = strtolower($name);

        return str_contains($name, '/') ? $name : "core/{$name}";
    }

    private function decodeAttributes(?string $json): array
    {
        if (! $json) {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function setRawClosing(Block $block, string $token): void
    {
        $reflection = new \ReflectionProperty($block, 'rawClosing');
        $reflection->setAccessible(true);
        $reflection->setValue($block, $token);
    }
}
