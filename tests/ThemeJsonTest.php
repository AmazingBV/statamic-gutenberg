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
                        ['name' => 'Fluid', 'slug' => 'fluid', 'size' => '30px', 'fluid' => ['min' => '18px', 'max' => '30px']],
                    ],
                ],
                'spacing' => [
                    'spacingSizes' => [
                        ['name' => 'Section', 'slug' => 'section', 'size' => '6rem'],
                    ],
                ],
                'dimensions' => [
                    'aspectRatios' => [
                        ['name' => 'Landscape', 'slug' => 'landscape', 'ratio' => '16/9'],
                    ],
                ],
                'shadow' => [
                    'presets' => [
                        ['name' => 'Natural', 'slug' => 'natural', 'shadow' => '6px 6px 9px rgba(0, 0, 0, 0.2)'],
                    ],
                ],
                'custom' => [
                    'radius' => [
                        'card' => '12px',
                    ],
                ],
            ],
            'styles' => [
                'background' => [
                    'backgroundImage' => [
                        'url' => 'file:./assets/images/background.jpg',
                    ],
                    'backgroundPosition' => 'center center',
                    'backgroundRepeat' => 'no-repeat',
                    'backgroundSize' => 'cover',
                ],
                'color' => [
                    'background' => 'var:preset|color|brand',
                    'text' => '#ffffff',
                ],
                'spacing' => [
                    'blockGap' => 'var:preset|spacing|section',
                ],
                'shadow' => 'var:preset|shadow|natural',
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
                        'dimensions' => [
                            'minHeight' => '12rem',
                            'minWidth' => '20rem',
                            'width' => '50%',
                        ],
                        'variations' => [
                            'lead' => [
                                'typography' => [
                                    'fontWeight' => '700',
                                ],
                            ],
                        ],
                    ],
                    'core/button' => [
                        'variations' => [
                            'outline' => [
                                'border' => [
                                    'color' => 'var:preset|color|brand',
                                    'radius' => [
                                        'topLeft' => '8px',
                                        'topRight' => '10px',
                                        'bottomLeft' => '12px',
                                        'bottomRight' => '14px',
                                    ],
                                    'style' => 'solid',
                                    'top' => [
                                        'color' => '#abcdef',
                                        'style' => 'dashed',
                                        'width' => '2px',
                                    ],
                                    'right' => '1px dotted #123456',
                                    'width' => '1px',
                                ],
                                'color' => [
                                    'background' => 'transparent',
                                    'text' => 'var:preset|color|brand',
                                ],
                            ],
                        ],
                    ],
                    'core/cover' => [
                        'dimensions' => [
                            'aspectRatio' => '16/9',
                            'height' => 'auto',
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
        $this->assertSame('16/9', $payload['settings']['dimensions']['aspectRatios'][0]['ratio']);
        $this->assertStringContainsString('--wp--style--global--content-size: 680px', $frontendCss);
        $this->assertStringContainsString('--wp--preset--color--brand: #123456', $frontendCss);
        $this->assertStringContainsString('--wp--preset--gradient--brand-gradient: linear-gradient(90deg,#123456,#abcdef)', $frontendCss);
        $this->assertStringContainsString('--wp--preset--font-family--inter: Inter, sans-serif', $frontendCss);
        $this->assertStringContainsString('--wp--preset--font-size--fluid: clamp(18px, calc(18px + 12px * ((100vw - 320px) / 1280)), 30px)', $frontendCss);
        $this->assertStringContainsString('--wp--preset--shadow--natural: 6px 6px 9px rgba(0, 0, 0, 0.2)', $frontendCss);
        $this->assertStringContainsString('@font-face', $frontendCss);
        $this->assertStringContainsString('font-family: Inter', $frontendCss);
        $this->assertStringContainsString('src: url("/vendor/statamic-gutenberg/theme/assets/fonts/inter-700.woff2")', $frontendCss);
        $this->assertStringContainsString('--wp--custom--radius--card: 12px', $frontendCss);
        $this->assertStringContainsString('.sgb-content .has-brand-color', $frontendCss);
        $this->assertStringContainsString('.sgb-content .has-natural-box-shadow', $frontendCss);
        $this->assertStringContainsString('background-image: url(/vendor/statamic-gutenberg/theme/assets/images/background.jpg)', $frontendCss);
        $this->assertStringContainsString('background-position: center center', $frontendCss);
        $this->assertStringContainsString('background-repeat: no-repeat', $frontendCss);
        $this->assertStringContainsString('background-size: cover', $frontendCss);
        $this->assertStringContainsString('background-color: var(--wp--preset--color--brand)', $frontendCss);
        $this->assertStringContainsString('box-shadow: var(--wp--preset--shadow--natural)', $frontendCss);
        $this->assertStringContainsString('gap: var(--wp--preset--spacing--section)', $frontendCss);
        $this->assertStringContainsString('.sgb-content a', $frontendCss);
        $this->assertStringContainsString('.sgb-content p', $frontendCss);
        $this->assertStringContainsString('font-size: var(--wp--preset--font-size--huge)', $frontendCss);
        $this->assertStringContainsString('min-height: 12rem', $frontendCss);
        $this->assertStringContainsString('min-width: 20rem', $frontendCss);
        $this->assertStringContainsString('width: 50%', $frontendCss);
        $this->assertStringContainsString('.sgb-content .wp-block-cover', $frontendCss);
        $this->assertStringContainsString('aspect-ratio: 16/9', $frontendCss);
        $this->assertStringContainsString('height: auto', $frontendCss);
        $this->assertStringContainsString('.sgb-content p.is-style-lead', $frontendCss);
        $this->assertStringContainsString('font-weight: 700 !important', $frontendCss);
        $this->assertStringContainsString('.sgb-content .wp-block-button.is-style-outline :is(.wp-element-button, .wp-block-button__link)', $frontendCss);
        $this->assertStringContainsString('border-color: var(--wp--preset--color--brand) !important', $frontendCss);
        $this->assertStringContainsString('border-top-left-radius: 8px !important', $frontendCss);
        $this->assertStringContainsString('border-top-right-radius: 10px !important', $frontendCss);
        $this->assertStringContainsString('border-bottom-left-radius: 12px !important', $frontendCss);
        $this->assertStringContainsString('border-bottom-right-radius: 14px !important', $frontendCss);
        $this->assertStringContainsString('border-top-color: #abcdef !important', $frontendCss);
        $this->assertStringContainsString('border-top-style: dashed !important', $frontendCss);
        $this->assertStringContainsString('border-top-width: 2px !important', $frontendCss);
        $this->assertStringContainsString('border-right: 1px dotted #123456 !important', $frontendCss);
        $this->assertStringContainsString('background-color: transparent !important', $frontendCss);
        $this->assertStringContainsString('.sgb-content .custom-theme-class', $frontendCss);
        $this->assertStringContainsString('.sgb-editor .sgb-page-frame', $payload['css']);
        $this->assertStringContainsString('.sgb-editor .sgb-canvas', $payload['css']);
        $this->assertStringNotContainsString('.editor-styles-wrapper', $payload['css']);
        $this->assertStringNotContainsString('.sgb-editor--fullscreen .sgb-page-frame', $payload['css']);
        $this->assertStringContainsString(
            'data-statamic-gutenberg-theme-json',
            (string) app(GutenbergManager::class)->frontendStyles()
        );
    }

    public function test_theme_json_ignores_unsafe_background_image_urls(): void
    {
        $this->writeThemeJson([
            'version' => 3,
            'styles' => [
                'background' => [
                    'backgroundImage' => [
                        'url' => 'javascript:alert(1)',
                    ],
                    'backgroundSize' => 'cover',
                ],
                'blocks' => [
                    'core/group' => [
                        'background' => [
                            'backgroundImage' => [
                                'url' => 'data:image/svg+xml;base64,PHN2Zy8+',
                            ],
                            'backgroundRepeat' => 'no-repeat',
                        ],
                    ],
                ],
            ],
        ]);

        $frontendCss = app(ThemeJson::class)->frontendCss();

        $this->assertStringContainsString('background-size: cover', $frontendCss);
        $this->assertStringContainsString('background-repeat: no-repeat', $frontendCss);
        $this->assertStringNotContainsString('background-image', $frontendCss);
        $this->assertStringNotContainsString('javascript:', $frontendCss);
        $this->assertStringNotContainsString('data:image', $frontendCss);
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

    public function test_theme_json_duotone_settings_filters_and_block_styles_are_exposed(): void
    {
        $coverDuotone = ['#123456', '#abcdef'];
        $coverFilterId = 'wp-duotone-theme-'.md5('core/cover'.serialize($coverDuotone));

        $this->writeThemeJson([
            'version' => 3,
            'settings' => [
                'color' => [
                    'duotone' => [
                        [
                            'name' => 'Brand Duo',
                            'slug' => 'brand-duo',
                            'colors' => ['#000000', '#ffffff'],
                        ],
                    ],
                ],
            ],
            'styles' => [
                'blocks' => [
                    'core/image' => [
                        'filter' => [
                            'duotone' => 'var:preset|duotone|brand-duo',
                        ],
                    ],
                    'core/cover' => [
                        'filter' => [
                            'duotone' => $coverDuotone,
                        ],
                    ],
                ],
            ],
        ]);

        $theme = app(ThemeJson::class);
        $payload = $theme->editorPayload();
        $frontendCss = $theme->frontendCss();
        $frontendStyles = (string) app(GutenbergManager::class)->frontendStyles();

        $this->assertSame('brand-duo', $payload['settings']['color']['duotone'][0]['slug']);
        $this->assertStringContainsString('--wp--preset--duotone--brand-duo: url(#wp-duotone-brand-duo)', $frontendCss);
        $this->assertStringContainsString('<filter id="wp-duotone-brand-duo"', $payload['svgs']);
        $this->assertStringContainsString('<filter id="'.$coverFilterId.'"', $payload['svgs']);
        $this->assertSame(1, substr_count($payload['svgs'], 'id="wp-duotone-brand-duo"'));
        $this->assertStringContainsString('.sgb-content .wp-block-image img, .sgb-content .wp-block-image .components-placeholder', $frontendCss);
        $this->assertStringContainsString('filter: url(#wp-duotone-brand-duo)', $frontendCss);
        $this->assertStringContainsString('.sgb-content .wp-block-cover > .wp-block-cover__image-background, .sgb-content .wp-block-cover > .wp-block-cover__video-background', $frontendCss);
        $this->assertStringContainsString('filter: url(#'.$coverFilterId.')', $frontendCss);
        $this->assertStringContainsString('<filter id="wp-duotone-brand-duo"', $frontendStyles);
        $this->assertStringContainsString('data-statamic-gutenberg-theme-json', $frontendStyles);
    }

    public function test_theme_json_custom_css_without_ampersand_is_scoped_to_content_roots(): void
    {
        $this->writeThemeJson([
            'version' => 3,
            'styles' => [
                'css' => '.custom-theme-class, button.custom-action { color: #123456; }',
            ],
        ]);

        $editorCss = app(ThemeJson::class)->editorCss();
        $frontendCss = app(ThemeJson::class)->frontendCss();

        $this->assertStringContainsString('.sgb-editor .sgb-page-frame .custom-theme-class', $editorCss);
        $this->assertStringContainsString('.sgb-editor .sgb-canvas button.custom-action', $editorCss);
        $this->assertStringContainsString('.sgb-content .custom-theme-class', $frontendCss);
        $this->assertStringNotContainsString("\n.custom-theme-class", $editorCss);
    }

    public function test_theme_json_custom_css_with_ampersand_is_expanded_per_content_root(): void
    {
        $this->writeThemeJson([
            'version' => 3,
            'styles' => [
                'css' => '& .custom-theme-class, & button.custom-action { color: var:preset|color|brand; }',
            ],
            'settings' => [
                'color' => [
                    'palette' => [
                        ['name' => 'Brand', 'slug' => 'brand', 'color' => '#123456'],
                    ],
                ],
            ],
        ]);

        $editorCss = app(ThemeJson::class)->editorCss();
        $frontendCss = app(ThemeJson::class)->frontendCss();

        $this->assertStringContainsString('.sgb-editor .sgb-page-frame .custom-theme-class', $editorCss);
        $this->assertStringContainsString('.sgb-editor .sgb-canvas .custom-theme-class', $editorCss);
        $this->assertStringContainsString('.sgb-editor .sgb-page-frame button.custom-action', $editorCss);
        $this->assertStringContainsString('.sgb-editor .sgb-canvas button.custom-action', $editorCss);
        $this->assertStringNotContainsString('.sgb-editor .sgb-page-frame, .sgb-editor .sgb-canvas .custom-theme-class', $editorCss);
        $this->assertStringContainsString('.sgb-content .custom-theme-class', $frontendCss);
        $this->assertStringContainsString('color: var(--wp--preset--color--brand)', $frontendCss);
    }

    public function test_theme_json_custom_css_inside_at_rules_is_scoped_to_content_roots(): void
    {
        $this->writeThemeJson([
            'version' => 3,
            'styles' => [
                'css' => '@media (min-width: 800px) { .custom-theme-class, button.custom-action:not(.components-button) { color: var:preset|color|brand; } } @supports (display: grid) { & .grid-only { display: grid; } } @keyframes pulse { from { opacity: 0; } to { opacity: 1; } }',
            ],
            'settings' => [
                'color' => [
                    'palette' => [
                        ['name' => 'Brand', 'slug' => 'brand', 'color' => '#123456'],
                    ],
                ],
            ],
        ]);

        $editorCss = app(ThemeJson::class)->editorCss();
        $frontendCss = app(ThemeJson::class)->frontendCss();

        $this->assertStringContainsString('@media (min-width: 800px) { .sgb-editor .sgb-page-frame .custom-theme-class, .sgb-editor .sgb-canvas .custom-theme-class, .sgb-editor .sgb-page-frame button.custom-action:not(.components-button), .sgb-editor .sgb-canvas button.custom-action:not(.components-button) { color: var(--wp--preset--color--brand); } }', $editorCss);
        $this->assertStringContainsString('@supports (display: grid) { .sgb-editor .sgb-page-frame .grid-only, .sgb-editor .sgb-canvas .grid-only { display: grid; } }', $editorCss);
        $this->assertStringContainsString('@media (min-width: 800px) { .sgb-content .custom-theme-class, .sgb-content button.custom-action:not(.components-button) { color: var(--wp--preset--color--brand); } }', $frontendCss);
        $this->assertStringContainsString('@supports (display: grid) { .sgb-content .grid-only { display: grid; } }', $frontendCss);
        $this->assertStringContainsString('@keyframes pulse { from { opacity: 0; } to { opacity: 1; } }', $editorCss);
        $this->assertStringNotContainsString('@media (min-width: 800px) { .custom-theme-class', $editorCss);
        $this->assertStringNotContainsString('@supports (display: grid) { & .grid-only', $frontendCss);
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
