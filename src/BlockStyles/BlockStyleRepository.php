<?php

namespace Amazingbv\StatamicGutenberg\BlockStyles;

use Illuminate\Support\Str;

class BlockStyleRepository
{
    private array $registered = [];

    public function path(): string
    {
        return (string) config('statamic-gutenberg.block_styles_path', resource_path('vendor/statamic-gutenberg/block-styles.php'));
    }

    public function register(string|array $blocks, array $style): self
    {
        $this->registered[] = [
            'blocks' => $blocks,
            'style' => $style,
        ];

        return $this;
    }

    public function editorPayload(?array $allowedBlocks = null): array
    {
        $allowed = $allowedBlocks === null ? null : array_flip($allowedBlocks);

        return collect($this->normalized())
            ->map(function (array $item) use ($allowed) {
                $blocks = $allowed === null
                    ? $item['blocks']
                    : array_values(array_filter($item['blocks'], fn (string $block) => isset($allowed[$block])));

                if (! $blocks) {
                    return null;
                }

                return [
                    'blocks' => $blocks,
                    'style' => [
                        'name' => $item['name'],
                        'label' => $item['label'],
                        'isDefault' => $item['isDefault'],
                        'source' => 'statamic',
                    ],
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function themeJsonBlocks(): array
    {
        $blocks = [];

        foreach ($this->normalized() as $item) {
            if (! $item['style']) {
                continue;
            }

            foreach ($item['blocks'] as $block) {
                $blocks[$block]['variations'][$item['name']] = $item['style'];
            }
        }

        return $blocks;
    }

    private function normalized(): array
    {
        $styles = [];

        foreach ($this->configured() as $item) {
            $this->addNormalized($styles, $item['blocks'], $item['style']);
        }

        foreach ($this->fileStyles() as $item) {
            $this->addNormalized($styles, $item['blocks'], $item['style']);
        }

        foreach ($this->registered as $item) {
            $this->addNormalized($styles, $item['blocks'], $item['style']);
        }

        return collect($styles)
            ->flatMap(fn (array $byStyle) => array_values($byStyle))
            ->values()
            ->all();
    }

    private function addNormalized(array &$styles, mixed $blocks, mixed $style): void
    {
        $blocks = $this->normalizeBlocks($blocks);
        $style = $this->normalizeStyle($style);

        if (! $blocks || ! $style) {
            return;
        }

        foreach ($blocks as $block) {
            $styles[$block][$style['name']] = [
                ...$style,
                'blocks' => [$block],
            ];
        }
    }

    private function configured(): array
    {
        return $this->itemsFromDefinition(config('statamic-gutenberg.block_styles', []));
    }

    private function fileStyles(): array
    {
        $path = $this->path();

        if (! is_file($path)) {
            return [];
        }

        $styles = include $path;

        return $this->itemsFromDefinition(is_array($styles) ? $styles : []);
    }

    private function itemsFromDefinition(mixed $definition): array
    {
        if (! is_array($definition)) {
            return [];
        }

        $items = [];

        foreach ($definition as $blocks => $styles) {
            if (is_string($blocks) && $this->validBlockName($blocks)) {
                foreach ($this->styleList($styles) as $style) {
                    $items[] = [
                        'blocks' => [$blocks],
                        'style' => $style,
                    ];
                }

                continue;
            }

            if (is_array($styles) && array_key_exists('blocks', $styles)) {
                $items[] = [
                    'blocks' => $styles['blocks'],
                    'style' => $styles,
                ];
            }
        }

        return $items;
    }

    private function styleList(mixed $styles): array
    {
        if (! is_array($styles)) {
            return [];
        }

        if (array_key_exists('name', $styles)) {
            return [$styles];
        }

        return collect($styles)
            ->filter(fn ($style) => is_array($style))
            ->values()
            ->all();
    }

    private function normalizeBlocks(mixed $blocks): array
    {
        if (is_string($blocks)) {
            $blocks = [$blocks];
        }

        if (! is_array($blocks)) {
            return [];
        }

        return collect($blocks)
            ->filter(fn ($block) => is_string($block) && $this->validBlockName($block))
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeStyle(mixed $style): ?array
    {
        if (! is_array($style)) {
            return null;
        }

        $name = $this->styleName($style['name'] ?? null);

        if (! $name) {
            return null;
        }

        return [
            'name' => $name,
            'label' => $this->label($style['label'] ?? null, $name),
            'isDefault' => (bool) ($style['isDefault'] ?? $style['is_default'] ?? false),
            'style' => is_array($style['style'] ?? null) ? $style['style'] : [],
        ];
    }

    private function styleName(mixed $name): ?string
    {
        if (! is_string($name)) {
            return null;
        }

        $name = preg_replace('/^is-style-/', '', trim($name)) ?? '';
        $slug = Str::slug($name);

        return $slug === '' ? null : $slug;
    }

    private function label(mixed $label, string $name): string
    {
        return is_string($label) && trim($label) !== ''
            ? trim($label)
            : Str::headline($name);
    }

    private function validBlockName(string $name): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9-]*\/[a-z0-9][a-z0-9-]*$/', $name);
    }
}
