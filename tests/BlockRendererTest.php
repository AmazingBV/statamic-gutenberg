<?php

namespace Amazingbv\StatamicGutenberg\Tests;

use Amazingbv\StatamicGutenberg\Blocks\BlockRenderer;
use Amazingbv\StatamicGutenberg\Blocks\BlockRegistry;
use Amazingbv\StatamicGutenberg\Blocks\CoreBlocks;
use Amazingbv\StatamicGutenberg\GutenbergManager;

class BlockRendererTest extends TestCase
{
    public function test_default_config_allows_all_installed_core_blocks(): void
    {
        $allowed = app(BlockRegistry::class)->allowedBlocks();

        $this->assertGreaterThanOrEqual(121, count(CoreBlocks::names()));
        $this->assertContains('core/cover', $allowed);
        $this->assertContains('core/media-text', $allowed);
        $this->assertContains('core/gallery', $allowed);
        $this->assertContains('core/query', $allowed);
        $this->assertContains('core/table', $allowed);
        $this->assertContains('core/video', $allowed);
        $this->assertContains('statamic/hero', $allowed);
    }

    public function test_it_renders_allowed_core_blocks_and_sanitizes_html(): void
    {
        $html = '<!-- wp:paragraph --><p onclick="alert(1)">Hello<script>alert(1)</script></p><!-- /wp:paragraph -->';

        $rendered = (string) app(BlockRenderer::class)->render($html);

        $this->assertStringContainsString('<p>Hello</p>', $rendered);
        $this->assertStringNotContainsString('onclick', $rendered);
        $this->assertStringNotContainsString('script', $rendered);
    }

    public function test_it_renders_static_markup_for_common_standard_core_blocks(): void
    {
        $html = implode('', [
            '<!-- wp:cover {"dimRatio":50,"customOverlayColor":"#fff","layout":{"type":"constrained"}} --><div class="wp-block-cover is-light"><span aria-hidden="true" class="wp-block-cover__background has-background-dim" style="background-color:#fff"></span><div class="wp-block-cover__inner-container"><!-- wp:paragraph --><p>Cover title</p><!-- /wp:paragraph --></div></div><!-- /wp:cover -->',
            '<!-- wp:media-text --><div class="wp-block-media-text"><figure class="wp-block-media-text__media"><img src="/storage/media.jpg" alt=""></figure><div class="wp-block-media-text__content"><!-- wp:paragraph --><p>Media text</p><!-- /wp:paragraph --></div></div><!-- /wp:media-text -->',
            '<!-- wp:gallery --><figure class="wp-block-gallery has-nested-images columns-default"><!-- wp:image --><figure class="wp-block-image"><img src="/storage/a.jpg" alt=""></figure><!-- /wp:image --></figure><!-- /wp:gallery -->',
            '<!-- wp:table --><figure class="wp-block-table"><table><tbody><tr><td>A</td></tr></tbody></table></figure><!-- /wp:table -->',
            '<!-- wp:details --><details class="wp-block-details"><summary>More</summary><p>Details</p></details><!-- /wp:details -->',
            '<!-- wp:video --><figure class="wp-block-video"><video controls src="/storage/movie.mp4"></video></figure><!-- /wp:video -->',
        ]);

        $rendered = (string) app(BlockRenderer::class)->render($html);

        $this->assertStringContainsString('class="wp-block-cover is-light"', $rendered);
        $this->assertStringContainsString('class="wp-block-media-text"', $rendered);
        $this->assertStringContainsString('class="wp-block-gallery has-nested-images columns-default"', $rendered);
        $this->assertStringContainsString('class="wp-block-table"', $rendered);
        $this->assertStringContainsString('class="wp-block-details"', $rendered);
        $this->assertStringContainsString('class="wp-block-video"', $rendered);
    }

    public function test_it_removes_transient_blob_media_urls_from_persisted_markup(): void
    {
        $html = '<!-- wp:cover {"url":"blob:https://site.test/temp"} --><div class="wp-block-cover"><img class="wp-block-cover__image-background" src="blob:https://site.test/temp"><p>Cover</p></div><!-- /wp:cover -->';

        $rendered = (string) app(BlockRenderer::class)->render($html);

        $this->assertStringContainsString('class="wp-block-cover"', $rendered);
        $this->assertStringContainsString('<p>Cover</p>', $rendered);
        $this->assertStringNotContainsString('blob:', $rendered);
    }

    public function test_it_preserves_safe_gutenberg_inline_styles(): void
    {
        $html = '<!-- wp:paragraph --><p style="margin-top:var(--wp--preset--spacing--40);padding:12px;color:#111;position:absolute;background-image:url(javascript:alert(1))">Styled</p><!-- /wp:paragraph -->';

        $rendered = (string) app(BlockRenderer::class)->render($html);

        $this->assertStringContainsString('margin-top: var(--wp--preset--spacing--40)', $rendered);
        $this->assertStringContainsString('padding: 12px', $rendered);
        $this->assertStringContainsString('color: #111', $rendered);
        $this->assertStringNotContainsString('position', $rendered);
        $this->assertStringNotContainsString('url(', $rendered);
    }

    public function test_it_preserves_safe_grid_layout_styles(): void
    {
        $html = '<!-- wp:group --><div class="wp-block-group is-layout-grid" style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem;position:absolute"><p>Grid</p></div><!-- /wp:group -->';

        $rendered = (string) app(BlockRenderer::class)->render($html);

        $this->assertStringContainsString('display: grid', $rendered);
        $this->assertStringContainsString('grid-template-columns: repeat(3,minmax(0,1fr))', $rendered);
        $this->assertStringContainsString('gap: 1rem', $rendered);
        $this->assertStringNotContainsString('position', $rendered);
    }

    public function test_it_adds_frontend_interaction_metadata_for_core_accordion_and_tabs(): void
    {
        $html = implode("\n", [
            '<!-- wp:accordion {"autoclose":true} --><div class="wp-block-accordion"><!-- wp:accordion-item {"openByDefault":true} --><div class="wp-block-accordion-item is-open"><!-- wp:accordion-heading {"title":"More"} --><h3 class="wp-block-accordion-heading"><button type="button" class="wp-block-accordion-heading__toggle"><span class="wp-block-accordion-heading__toggle-title">More</span></button></h3><!-- /wp:accordion-heading --><!-- wp:accordion-panel --><div class="wp-block-accordion-panel"><!-- wp:paragraph --><p>Panel</p><!-- /wp:paragraph --></div><!-- /wp:accordion-panel --></div><!-- /wp:accordion-item --></div><!-- /wp:accordion -->',
            '<!-- wp:tabs {"activeTabIndex":1} --><div class="wp-block-tabs"><!-- wp:tab-list --><div class="wp-block-tab-list"><!-- wp:tab --><button type="button" role="tab" class="wp-block-tab"></button><!-- /wp:tab --><!-- wp:tab --><button type="button" role="tab" class="wp-block-tab"></button><!-- /wp:tab --></div><!-- /wp:tab-list --><!-- wp:tab-panels --><div class="wp-block-tab-panels"><!-- wp:tab-panel {"label":"First"} --><section class="wp-block-tab-panel"><p>First panel</p></section><!-- /wp:tab-panel --><!-- wp:tab-panel {"label":"Second"} --><section class="wp-block-tab-panel"><p>Second panel</p></section><!-- /wp:tab-panel --></div><!-- /wp:tab-panels --></div><!-- /wp:tabs -->',
        ]);

        $rendered = (string) app(BlockRenderer::class)->render($html);

        $this->assertStringContainsString('data-sgb-accordion-autoclose="true"', $rendered);
        $this->assertStringContainsString('data-sgb-active-tab-index="1"', $rendered);
        $this->assertStringContainsString('role="tablist"', $rendered);
        $this->assertStringContainsString('data-sgb-tab-label="First"', $rendered);
        $this->assertStringContainsString('data-sgb-tab-label="Second"', $rendered);
    }

    public function test_it_preserves_wrapper_block_attributes(): void
    {
        $html = '<!-- wp:group --><div class="wp-block-group alignwide has-background" style="padding-top:var(--wp--preset--spacing--50)"><!-- wp:paragraph --><p>Inner</p><!-- /wp:paragraph --></div><!-- /wp:group -->';

        $rendered = (string) app(BlockRenderer::class)->render($html);

        $this->assertStringContainsString('class="wp-block-group alignwide has-background"', $rendered);
        $this->assertStringContainsString('style="padding-top: var(--wp--preset--spacing--50)"', $rendered);
        $this->assertStringContainsString('<p>Inner</p>', $rendered);
    }

    public function test_it_adds_frontend_fit_text_classes_to_headings(): void
    {
        $html = '<!-- wp:heading {"fitText":true} --><h2>Scale me</h2><!-- /wp:heading -->';

        $rendered = (string) app(BlockRenderer::class)->render($html);

        $this->assertStringContainsString('class="wp-block-heading has-fit-text"', $rendered);
        $this->assertStringContainsString('<h2', $rendered);
    }

    public function test_it_adds_lightbox_frontend_markup_to_enabled_images(): void
    {
        $html = '<!-- wp:image {"lightbox":{"enabled":true}} --><figure class="wp-block-image"><img src="/assets/photo.jpg" alt="Photo"></figure><!-- /wp:image -->';

        $rendered = (string) app(BlockRenderer::class)->render($html);

        $this->assertStringContainsString('wp-lightbox-container', $rendered);
        $this->assertStringContainsString('data-sgb-lightbox="true"', $rendered);
        $this->assertStringContainsString('data-sgb-lightbox-trigger="true"', $rendered);
    }

    public function test_it_blocks_unknown_blocks_by_default(): void
    {
        $html = '<!-- wp:plugin/card --><section>Plugin output</section><!-- /wp:plugin/card -->';

        $rendered = (string) app(BlockRenderer::class)->render($html);

        $this->assertSame('', trim($rendered));
    }

    public function test_facade_can_register_runtime_blocks(): void
    {
        app(GutenbergManager::class)->block('statamic/test', function ($block) {
            return '<strong>'.e($block->attribute('label')).'</strong>';
        });

        $html = '<!-- wp:statamic/test {"label":"Facade"} --><div></div><!-- /wp:statamic/test -->';
        $rendered = (string) app(GutenbergManager::class)->render($html, [
            'allowed_blocks' => ['statamic/test'],
        ]);

        $this->assertSame('<strong>Facade</strong>', $rendered);
    }
}
