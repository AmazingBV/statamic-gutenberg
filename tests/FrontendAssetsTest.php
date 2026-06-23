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
        $this->assertStringContainsString('grid-template-columns: repeat(auto-fill, minmax(min(12rem, 100%), 1fr))', $css);
        $this->assertStringContainsString('.site-content.sgb-content > * + *', $css);
        $this->assertStringContainsString('margin-block-start: 0 !important', $css);
        $this->assertStringContainsString('.sgb-content :where(.is-layout-flex, .is-layout-grid) > * + *', $css);
        $this->assertStringContainsString('.sgb-content :where(ul.wp-block-list)', $css);
        $this->assertStringContainsString('.sgb-content :where(.has-text-align-justify)', $css);
        $this->assertStringContainsString('.sgb-content :where(.has-blue-color)', $css);
        $this->assertStringContainsString('.site-content.sgb-content > .alignfull', $css);
        $this->assertStringContainsString('.sgb-content > :where(.wp-block-columns.alignfull, .wp-block-group.alignfull)', $css);
        $this->assertStringContainsString('grid-column: 1 / -1', $css);
        $this->assertStringContainsString('width: 100vw', $css);
        $this->assertStringContainsString('max-width: none', $css);
        $this->assertStringContainsString('margin-inline-start: calc(50% - 50vw)', $css);
        $this->assertStringContainsString('.sgb-content :where(.is-layout-constrained) > :where(:not(.alignleft):not(.alignright):not(.alignfull))', $css);
        $this->assertStringContainsString('max-width: var(--wp--style--global--content-size)', $css);
        $this->assertStringContainsString('.sgb-content :where(.is-layout-constrained) > .alignwide', $css);
        $this->assertStringContainsString('max-width: var(--wp--style--global--wide-size)', $css);
        $this->assertStringNotContainsString('grid-column: full !important', $css);
        $this->assertStringNotContainsString(".site-content.sgb-content > .alignfull {\n    grid-column: wide;", $css);
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
