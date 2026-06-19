<?php

namespace Amazingbv\StatamicGutenberg;

use Amazingbv\StatamicGutenberg\Blocks\BlockParser;
use Amazingbv\StatamicGutenberg\Blocks\BlockRegistry;
use Amazingbv\StatamicGutenberg\Blocks\BlockRenderer;
use Illuminate\Support\HtmlString;

class GutenbergManager
{
    public function __construct(
        private BlockRegistry $registry,
        private BlockParser $parser,
        private BlockRenderer $renderer,
    ) {
        //
    }

    public function block(string $name, array|string|callable $definition): self
    {
        $this->registry->block($name, $definition);

        return $this;
    }

    public function parse(?string $content): array
    {
        return $this->parser->parse($content);
    }

    public function serialize(array $blocks): string
    {
        return $this->parser->serialize($blocks);
    }

    public function render(?string $content, array $options = []): HtmlString
    {
        return $this->renderer->render($content, $options);
    }

    public function allowedBlocks(?array $fieldAllowed = null): array
    {
        return $this->registry->allowedBlocks($fieldAllowed);
    }
}
