<?php

namespace Amazingbv\StatamicGutenberg\Tests;

use Amazingbv\StatamicGutenberg\BlockStyles\BlockStyleRepository;
use Amazingbv\StatamicGutenberg\GutenbergManager;
use Amazingbv\StatamicGutenberg\ThemeJson;

class BlockStyleRepositoryTest extends TestCase
{
    private ?string $blockStylesPath = null;

    protected function tearDown(): void
    {
        if ($this->blockStylesPath && is_file($this->blockStylesPath)) {
            unlink($this->blockStylesPath);
        }

        parent::tearDown();
    }

    public function test_config_file_and_runtime_block_styles_are_exposed_to_editor(): void
    {
        config(['statamic-gutenberg.block_styles' => [
            'core/paragraph' => [
                [
                    'name' => 'lead',
                    'label' => 'Lead',
                    'style' => [
                        'typography' => ['fontWeight' => '700'],
                    ],
                ],
            ],
            [
                'blocks' => ['core/heading', 'core/paragraph'],
                'name' => 'eyebrow',
                'label' => 'Eyebrow',
            ],
        ]]);
        $this->writeBlockStylesFile([
            'core/button' => [
                [
                    'name' => 'brand-button',
                    'label' => 'Brand button',
                    'is_default' => true,
                ],
            ],
        ]);

        app(GutenbergManager::class)->blockStyle('core/quote', [
            'name' => 'pull',
            'label' => 'Pull quote',
        ]);

        $payload = app(GutenbergManager::class)->editorBlockStyles([
            'core/paragraph',
            'core/button',
            'core/quote',
        ]);

        $this->assertContains('lead', array_column(array_column($payload, 'style'), 'name'));
        $this->assertContains('eyebrow', array_column(array_column($payload, 'style'), 'name'));
        $this->assertContains('brand-button', array_column(array_column($payload, 'style'), 'name'));
        $this->assertContains('pull', array_column(array_column($payload, 'style'), 'name'));
        $this->assertNotContains('core/heading', collect($payload)->flatMap(fn (array $item) => $item['blocks'])->all());
        $this->assertTrue(collect($payload)->firstWhere('style.name', 'brand-button')['style']['isDefault']);
        $this->assertSame('statamic', collect($payload)->firstWhere('style.name', 'lead')['style']['source']);
    }

    public function test_invalid_definitions_are_ignored_and_missing_labels_are_normalized(): void
    {
        config(['statamic-gutenberg.block_styles' => [
            'invalid' => [
                ['name' => 'bad'],
            ],
            'core/paragraph' => [
                ['name' => ''],
                ['name' => 'no-label'],
            ],
            [
                'blocks' => ['bad', 'core/heading'],
                'name' => 'is-style-hero-title',
            ],
        ]]);
        $this->refreshBlockStyleInstances();

        $payload = app(BlockStyleRepository::class)->editorPayload();

        $this->assertSame(['no-label', 'hero-title'], array_column(array_column($payload, 'style'), 'name'));
        $this->assertSame('No Label', $payload[0]['style']['label']);
        $this->assertSame(['core/heading'], $payload[1]['blocks']);
    }

    public function test_block_style_css_is_generated_for_editor_and_frontend_without_theme_json(): void
    {
        config([
            'statamic-gutenberg.theme_json_path' => sys_get_temp_dir().'/missing-statamic-gutenberg-theme.json',
            'statamic-gutenberg.block_styles' => [
                'core/paragraph' => [
                    [
                        'name' => 'lead',
                        'style' => [
                            'typography' => ['fontWeight' => '700'],
                        ],
                    ],
                ],
                'core/button' => [
                    [
                        'name' => 'brand-button',
                        'style' => [
                            'border' => [
                                'color' => '#123456',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $this->refreshBlockStyleInstances();

        $theme = app(ThemeJson::class);
        $frontendCss = $theme->frontendCss();
        $editorCss = $theme->editorCss();
        $payload = $theme->editorPayload();

        $this->assertStringContainsString('.sgb-content p.is-style-lead', $frontendCss);
        $this->assertStringContainsString('font-weight: 700 !important', $frontendCss);
        $this->assertStringContainsString('.sgb-editor .sgb-page-frame p.is-style-lead', $editorCss);
        $this->assertStringContainsString('.sgb-content .wp-block-button.is-style-brand-button :is(.wp-element-button, .wp-block-button__link)', $frontendCss);
        $this->assertStringContainsString('border-color: #123456 !important', $frontendCss);
        $this->assertIsArray($payload);
        $this->assertSame($editorCss, $payload['css']);
    }

    private function writeBlockStylesFile(array $styles): void
    {
        $this->blockStylesPath = sys_get_temp_dir().'/sgb-block-styles-'.bin2hex(random_bytes(6)).'.php';
        file_put_contents($this->blockStylesPath, '<?php return '.var_export($styles, true).';');
        config(['statamic-gutenberg.block_styles_path' => $this->blockStylesPath]);
        $this->refreshBlockStyleInstances();
    }

    private function refreshBlockStyleInstances(): void
    {
        $this->app->forgetInstance(BlockStyleRepository::class);
        $this->app->forgetInstance(ThemeJson::class);
        $this->app->forgetInstance(GutenbergManager::class);
    }
}
