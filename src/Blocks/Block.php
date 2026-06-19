<?php

namespace Amazingbv\StatamicGutenberg\Blocks;

class Block
{
    public function __construct(
        private string $name,
        private array $attributes = [],
        private array $innerBlocks = [],
        private array $innerContent = [],
        private ?string $rawOpening = null,
        private ?string $rawClosing = null,
        private bool $selfClosing = false,
    ) {
        //
    }

    public static function freeform(string $html): self
    {
        return new self('core/freeform', [], [], [$html]);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function attributes(): array
    {
        return $this->attributes;
    }

    public function attribute(string $key, mixed $fallback = null): mixed
    {
        return $this->attributes[$key] ?? $fallback;
    }

    public function innerBlocks(): array
    {
        return $this->innerBlocks;
    }

    public function innerContent(): array
    {
        return $this->innerContent;
    }

    public function isFreeform(): bool
    {
        return $this->name === 'core/freeform';
    }

    public function addHtml(string $html): void
    {
        if ($html === '') {
            return;
        }

        $last = array_key_last($this->innerContent);

        if ($last !== null && is_string($this->innerContent[$last])) {
            $this->innerContent[$last] .= $html;

            return;
        }

        $this->innerContent[] = $html;
    }

    public function addInnerBlock(self $block): void
    {
        $this->innerBlocks[] = $block;
        $this->innerContent[] = null;
    }

    public function innerHtml(): string
    {
        return collect($this->innerContent)
            ->filter(fn ($content) => is_string($content))
            ->implode('');
    }

    public function renderableHtml(): string
    {
        $html = '';
        $blockIndex = 0;

        foreach ($this->innerContent as $content) {
            if (is_string($content)) {
                $html .= $content;

                continue;
            }

            if (isset($this->innerBlocks[$blockIndex])) {
                $html .= $this->innerBlocks[$blockIndex]->renderableHtml();
            }

            $blockIndex++;
        }

        return $html;
    }

    public function serialize(): string
    {
        if ($this->isFreeform()) {
            return $this->renderableHtml();
        }

        if ($this->selfClosing && empty($this->innerBlocks) && $this->innerHtml() === '') {
            return $this->rawOpening ?? $this->openingComment(true);
        }

        $html = $this->rawOpening ?? $this->openingComment();
        $blockIndex = 0;

        foreach ($this->innerContent as $content) {
            if (is_string($content)) {
                $html .= $content;

                continue;
            }

            if (isset($this->innerBlocks[$blockIndex])) {
                $html .= $this->innerBlocks[$blockIndex]->serialize();
            }

            $blockIndex++;
        }

        return $html.($this->rawClosing ?? $this->closingComment());
    }

    private function openingComment(bool $selfClosing = false): string
    {
        $attributes = $this->attributes
            ? ' '.json_encode($this->attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : '';

        return sprintf('<!-- wp:%s%s%s-->', $this->commentName(), $attributes, $selfClosing ? ' /' : ' ');
    }

    private function closingComment(): string
    {
        return sprintf('<!-- /wp:%s -->', $this->commentName());
    }

    private function commentName(): string
    {
        if (str_starts_with($this->name, 'core/')) {
            return substr($this->name, 5);
        }

        return $this->name;
    }
}
