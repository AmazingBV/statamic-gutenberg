<?php

namespace Amazingbv\StatamicGutenberg\Tests;

use Amazingbv\StatamicGutenberg\Blocks\BlockRenderer;
use Amazingbv\StatamicGutenberg\Blocks\CoreBlocks;

class CoreBlocksCoverageTest extends TestCase
{
    public function test_core_block_list_matches_installed_block_library_metadata(): void
    {
        $this->assertSame(
            $this->installedCoreBlockNames(),
            $this->sorted(CoreBlocks::names())
        );
    }

    public function test_frontend_view_block_list_matches_installed_view_modules(): void
    {
        $this->assertSame(
            $this->installedFrontendViewBlocks(),
            $this->sorted(CoreBlocks::frontendViewBlocks())
        );
    }

    public function test_runtime_fallback_list_covers_core_blocks_without_static_save_markup(): void
    {
        $missing = array_values(array_diff(
            $this->installedBlocksWithoutStaticSave(),
            CoreBlocks::runtimeFallbackBlocks()
        ));

        $this->assertSame([], $missing);
    }

    public function test_saved_markup_for_every_installed_core_block_is_not_dropped_by_renderer(): void
    {
        $renderer = app(BlockRenderer::class);

        foreach (CoreBlocks::names() as $name) {
            $label = 'Rendered '.$name;
            $html = sprintf(
                '<!-- wp:%s --><div class="%s">%s</div><!-- /wp:%s -->',
                $this->commentName($name),
                $this->coreBlockClass($name),
                $label,
                $this->commentName($name),
            );

            $rendered = (string) $renderer->render($html, $this->allCoreAllowedOptions());

            $this->assertStringContainsString($label, $rendered, "{$name} dropped saved markup.");
            $this->assertStringNotContainsString('<!-- wp:', $rendered, "{$name} leaked block comments.");
        }
    }

    public function test_every_runtime_fallback_core_block_renders_non_empty_without_saved_markup(): void
    {
        $renderer = app(BlockRenderer::class);

        foreach (array_diff(CoreBlocks::runtimeFallbackBlocks(), ['core/icon']) as $name) {
            $rendered = trim((string) $renderer->render(sprintf('<!-- wp:%s /-->', $this->commentName($name)), $this->allCoreAllowedOptions()));

            $this->assertNotSame('', $rendered, "{$name} has no static save markup and no visible fallback output.");
            $this->assertMatchesRegularExpression('/wp-block-|data-sgb-core-fallback/', $rendered, "{$name} fallback lacks core block marker.");
        }
    }

    private function installedCoreBlockNames(): array
    {
        return $this->sorted(collect(glob($this->blockLibraryPath().'/*/block.json') ?: [])
            ->map(fn ($path) => json_decode(file_get_contents($path), true)['name'] ?? null)
            ->filter()
            ->all());
    }

    private function installedFrontendViewBlocks(): array
    {
        return $this->sorted(collect(glob($this->blockLibraryPath().'/*/view.mjs') ?: [])
            ->map(fn ($path) => 'core/'.basename(dirname($path)))
            ->all());
    }

    private function installedBlocksWithoutStaticSave(): array
    {
        return $this->sorted(collect(glob($this->blockLibraryPath().'/*/block.json') ?: [])
            ->filter(fn ($path) => ! file_exists(dirname($path).'/save.mjs'))
            ->map(fn ($path) => json_decode(file_get_contents($path), true)['name'] ?? null)
            ->filter()
            ->all());
    }

    private function blockLibraryPath(): string
    {
        $path = __DIR__.'/../node_modules/@wordpress/block-library/build-module';

        if (! is_dir($path)) {
            $this->markTestSkipped('WordPress block-library metadata is not installed.');
        }

        return $path;
    }

    private function allCoreAllowedOptions(): array
    {
        return [
            'allowed_blocks' => CoreBlocks::names(),
        ];
    }

    private function sorted(array $items): array
    {
        sort($items);

        return array_values($items);
    }

    private function commentName(string $name): string
    {
        return str_starts_with($name, 'core/') ? substr($name, 5) : $name;
    }

    private function coreBlockClass(string $name): string
    {
        return 'wp-block-'.str_replace('_', '-', $this->commentName($name));
    }
}
