<?php

namespace Amazingbv\StatamicGutenberg\Tests;

use Amazingbv\StatamicGutenberg\GutenbergManager;
use Amazingbv\StatamicGutenberg\ThemeJson;

class ThemeJsonTest extends TestCase
{
    private ?string $themeJsonPath = null;
    private ?string $themeJsonDirectory = null;

    protected function tearDown(): void
    {
        if ($this->themeJsonPath && is_file($this->themeJsonPath)) {
            unlink($this->themeJsonPath);
        }

        if ($this->themeJsonDirectory && is_dir($this->themeJsonDirectory)) {
            $this->deleteDirectory($this->themeJsonDirectory);
        }

        parent::tearDown();
    }

    public function test_missing_theme_json_does_not_add_editor_payload_or_frontend_css(): void
    {
        config(['statamic-gutenberg.theme_json_path' => sys_get_temp_dir().'/missing-statamic-gutenberg-theme.json']);
        $this->refreshThemeJsonInstances();

        $this->assertNull(app(ThemeJson::class)->editorPayload());
        $this->assertSame('', app(ThemeJson::class)->frontendCss());
        $this->assertStringNotContainsString(
            'data-statamic-gutenberg-theme-json',
            (string) app(GutenbergManager::class)->frontendStyles()
        );
    }

    public function test_theme_json_settings_and_styles_are_exposed_to_editor_and_frontend(): void
    {
        $this->writeThemeJson([
            'version' => 3,
            'settings' => [
                'layout' => [
                    'contentSize' => '680px',
                    'wideSize' => '1180px',
                ],
                'color' => [
                    'custom' => false,
                    'palette' => [
                        ['name' => 'Brand', 'slug' => 'brand', 'color' => '#123456'],
                    ],
                    'gradients' => [
                        ['name' => 'Brand Gradient', 'slug' => 'brand-gradient', 'gradient' => 'linear-gradient(90deg,#123456,#abcdef)'],
                    ],
                ],
                'typography' => [
                    'fontFamilies' => [
                        [
                            'name' => 'Inter',
                            'slug' => 'inter',
                            'fontFamily' => 'Inter, sans-serif',
                            'fontFace' => [
                                [
                                    'fontFamily' => 'Inter',
                                    'fontStyle' => 'normal',
                                    'fontWeight' => '700',
                                    'src' => ['file:./assets/fonts/inter-700.woff2'],
                                ],
                            ],
                        ],
                    ],
                    'fontSizes' => [
                        ['name' => 'Huge', 'slug' => 'huge', 'size' => '4rem'],
                    ],
                ],
                'spacing' => [
                    'spacingSizes' => [
                        ['name' => 'Section', 'slug' => 'section', 'size' => '6rem'],
                    ],
                ],
                'custom' => [
                    'radius' => [
                        'card' => '12px',
                    ],
                ],
            ],
            'styles' => [
                'color' => [
                    'background' => 'var:preset|color|brand',
                    'text' => '#ffffff',
                ],
                'spacing' => [
                    'blockGap' => 'var:preset|spacing|section',
                ],
                'elements' => [
                    'link' => [
                        'color' => [
                            'text' => '#abcdef',
                        ],
                    ],
                ],
                'blocks' => [
                    'core/paragraph' => [
                        'typography' => [
                            'fontSize' => 'var:preset|font-size|huge',
                        ],
                    ],
                ],
                'css' => '& .custom-theme-class { color: var:preset|color|brand; }',
            ],
        ]);

        $theme = app(ThemeJson::class);
        $payload = $theme->editorPayload();
        $frontendCss = $theme->frontendCss();

        $this->assertSame('680px', $payload['settings']['layout']['contentSize']);
        $this->assertSame('#123456', $payload['settings']['color']['palette'][0]['color']);
        $this->assertStringContainsString('--wp--style--global--content-size: 680px', $frontendCss);
        $this->assertStringContainsString('--wp--preset--color--brand: #123456', $frontendCss);
        $this->assertStringContainsString('--wp--preset--gradient--brand-gradient: linear-gradient(90deg,#123456,#abcdef)', $frontendCss);
        $this->assertStringContainsString('--wp--preset--font-family--inter: Inter, sans-serif', $frontendCss);
        $this->assertStringContainsString('@font-face', $frontendCss);
        $this->assertStringContainsString('font-family: Inter', $frontendCss);
        $this->assertStringContainsString('src: url("/vendor/statamic-gutenberg/theme/assets/fonts/inter-700.woff2")', $frontendCss);
        $this->assertStringContainsString('--wp--custom--radius--card: 12px', $frontendCss);
        $this->assertStringContainsString('.sgb-content .has-brand-color', $frontendCss);
        $this->assertStringContainsString('background-color: var(--wp--preset--color--brand)', $frontendCss);
        $this->assertStringContainsString('gap: var(--wp--preset--spacing--section)', $frontendCss);
        $this->assertStringContainsString('.sgb-content a', $frontendCss);
        $this->assertStringContainsString('.sgb-content p', $frontendCss);
        $this->assertStringContainsString('font-size: var(--wp--preset--font-size--huge)', $frontendCss);
        $this->assertStringContainsString('.sgb-content .custom-theme-class', $frontendCss);
        $this->assertStringContainsString('.sgb-editor .sgb-canvas', $payload['css']);
        $this->assertStringNotContainsString('.editor-styles-wrapper', $payload['css']);
        $this->assertStringNotContainsString('.sgb-editor--fullscreen .sgb-page-frame', $payload['css']);
        $this->assertStringContainsString(
            'data-statamic-gutenberg-theme-json',
            (string) app(GutenbergManager::class)->frontendStyles()
        );
    }

    public function test_editor_button_styles_do_not_target_gutenberg_toolbar_buttons(): void
    {
        $this->writeThemeJson([
            'version' => 3,
            'styles' => [
                'elements' => [
                    'button' => [
                        'color' => [
                            'background' => '#ee2b0b',
                            'text' => '#ffffff',
                        ],
                    ],
                ],
            ],
        ]);

        $css = app(ThemeJson::class)->editorCss();

        $this->assertStringContainsString('.sgb-editor .sgb-canvas :is(.wp-element-button, .wp-block-button__link, button:not(.components-button)', $css);
        $this->assertStringContainsString(':not([class*="block-editor-"])', $css);
        $this->assertStringNotContainsString('.sgb-editor--fullscreen .sgb-page-frame :is(.wp-element-button, .wp-block-button__link, button)', $css);
    }

    public function test_theme_json_relative_assets_are_served_from_theme_directory(): void
    {
        $this->writeThemeJson([
            'version' => 3,
            'settings' => [
                'typography' => [
                    'fontFamilies' => [
                        [
                            'name' => 'Inter',
                            'slug' => 'inter',
                            'fontFamily' => 'Inter, sans-serif',
                            'fontFace' => [
                                [
                                    'fontFamily' => 'Inter',
                                    'src' => ['file:./assets/fonts/inter.woff2'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], [
            'assets/fonts/inter.woff2' => 'font-data',
        ]);

        $this->get('/vendor/statamic-gutenberg/theme/assets/fonts/inter.woff2')
            ->assertOk()
            ->assertHeader('cache-control', 'max-age=31536000, public');
    }

    private function writeThemeJson(array $data, array $assets = []): void
    {
        $this->themeJsonDirectory = sys_get_temp_dir().'/sgb-theme-json-'.bin2hex(random_bytes(6));
        mkdir($this->themeJsonDirectory, 0777, true);

        foreach ($assets as $path => $contents) {
            $file = $this->themeJsonDirectory.'/'.ltrim($path, '/');
            mkdir(dirname($file), 0777, true);
            file_put_contents($file, $contents);
        }

        $this->themeJsonPath = $this->themeJsonDirectory.'/theme.json';
        file_put_contents($this->themeJsonPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        config(['statamic-gutenberg.theme_json_path' => $this->themeJsonPath]);
        $this->refreshThemeJsonInstances();
    }

    private function refreshThemeJsonInstances(): void
    {
        $this->app->forgetInstance(ThemeJson::class);
        $this->app->forgetInstance(GutenbergManager::class);
    }

    private function deleteDirectory(string $directory): void
    {
        foreach (array_diff(scandir($directory) ?: [], ['.', '..']) as $item) {
            $path = $directory.'/'.$item;

            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        rmdir($directory);
    }
}
