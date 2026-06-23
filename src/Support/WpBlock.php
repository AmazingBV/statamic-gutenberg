<?php

namespace Amazingbv\StatamicGutenberg\Support;

use Amazingbv\StatamicGutenberg\Blocks\Block;
use Amazingbv\StatamicGutenberg\Blocks\BlockRenderer;

class WpBlock
{
    public array $attributes;

    public array $inner_blocks;

    public array $parsed_block;

    public function __construct(
        private Block $block,
        private BlockRenderer $renderer,
        private array $options = [],
    ) {
        $this->attributes = $block->attributes();
        $this->inner_blocks = array_map(
            fn (Block $innerBlock) => new self($innerBlock, $renderer, $options),
            $block->innerBlocks()
        );
        $this->parsed_block = $this->parsedBlock($block);
    }

    public function render(): string
    {
        return $this->renderer->renderBlock($this->block, $this->options);
    }

    private function parsedBlock(Block $block): array
    {
        return [
            'blockName' => $block->isFreeform() ? null : $block->name(),
            'attrs' => $block->attributes(),
            'innerBlocks' => array_map(
                fn (WpBlock $innerBlock) => $innerBlock->parsed_block,
                $this->inner_blocks
            ),
            'innerHTML' => $block->innerHtml(),
            'innerContent' => $block->innerContent(),
        ];
    }
}
