<?php

namespace Amazingbv\StatamicGutenberg\Tests;

use Amazingbv\StatamicGutenberg\GutenbergManager;

class FrontendAssetsTest extends TestCase
{
    public function test_frontend_css_includes_core_block_library_and_layout_helpers(): void
    {
        $css = file_get_contents(__DIR__.'/../resources/css/frontend.css');

        $this->assertStringContainsString('@wordpress/block-library/build-style/style.css', $css);
        $this->assertStringContainsString('@wordpress/block-library/build-style/theme.css', $css);
        $this->assertStringContainsString('.sgb-content :where(.is-layout-grid)', $css);
        $this->assertStringContainsString('.sgb-content :where(ul.wp-block-list)', $css);
        $this->assertStringContainsString('.sgb-content :where(.sgb-core-fallback)', $css);
        $this->assertStringContainsString('.sgb-lightbox', $css);
    }

    public function test_frontend_javascript_enhances_interactive_standard_blocks(): void
    {
        $js = file_get_contents(__DIR__.'/../resources/js/frontend.js');

        $this->assertStringContainsString('initAccordions', $js);
        $this->assertStringContainsString('initTabs', $js);
        $this->assertStringContainsString('initLightbox', $js);
        $this->assertStringContainsString('initFitText', $js);
        $this->assertStringContainsString('initSearchBlocks', $js);
        $this->assertStringContainsString('initNavigationBlocks', $js);
        $this->assertStringContainsString('initFileBlocks', $js);
        $this->assertStringContainsString('initFormBlocks', $js);
    }

    public function test_frontend_assets_are_exposed_through_manager_and_tag(): void
    {
        $manager = app(GutenbergManager::class);

        $this->assertTrue(app('statamic.tags')->has('gutenberg'));
        $this->assertStringContainsString('/vendor/statamic-gutenberg/build/assets/frontend-', (string) $manager->frontendStyles());
        $this->assertStringContainsString('rel="stylesheet"', (string) $manager->frontendStyles());
        $this->assertStringContainsString('/vendor/statamic-gutenberg/build/assets/frontend-', (string) $manager->frontendScripts());
        $this->assertStringContainsString('type="module"', (string) $manager->frontendScripts());
    }
}
