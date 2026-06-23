<?php

namespace Amazingbv\StatamicGutenberg\Blocks;

class BlockRegistry
{
    private array $blocks = [];

    public function block(string $name, array|string|callable $definition): self
    {
        $this->blocks[$name] = is_string($definition)
            ? ['view' => $definition]
            : $definition;

        return $this;
    }

    public function definition(string $name): array|string|callable|null
    {
        return $this->blocks[$name]
            ?? config("statamic-gutenberg.blocks.{$name}");
    }

    public function allowedBlocks(?array $fieldAllowed = null): array
    {
        $allowed = $fieldAllowed ?: config('statamic-gutenberg.allowed_blocks', []);
        $allowed[] = 'core/block';

        return array_values(array_unique(array_filter($allowed)));
    }

    public function isAllowed(string $name, ?array $fieldAllowed = null): bool
    {
        return in_array($name, $this->allowedBlocks($fieldAllowed), true);
    }
}
