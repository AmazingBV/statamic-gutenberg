<?php

namespace Amazingbv\StatamicGutenberg\Tests;

use Amazingbv\StatamicGutenberg\Blocks\BlockRenderer;
use Amazingbv\StatamicGutenberg\Blocks\BlockRegistry;
use Amazingbv\StatamicGutenberg\Blocks\CoreBlocks;
use Amazingbv\StatamicGutenberg\GutenbergManager;

class BlockRendererTest extends TestCase
{
    public function test_default_config_allows_only_the_home_block_profile(): void
    {
        $allowed = app(BlockRegistry::class)->allowedBlocks();

        $this->assertGreaterThanOrEqual(121, count(CoreBlocks::names()));
        $this->assertLessThan(count(CoreBlocks::names()), count($allowed));
        $this->assertContains('core/cover', $allowed);
        $this->assertContains('core/media-text', $allowed);
        $this->assertContains('core/gallery', $allowed);
        $this->assertContains('core/table', $allowed);
        $this->assertContains('core/video', $allowed);
        $this->assertContains('core/icon', $allowed);
        $this->assertContains('statamic/hero', $allowed);
        $this->assertNotContains('core/query', $allowed);
        $this->assertNotContains('core/form', $allowed);
        $this->assertNotContains('core/tabs', $allowed);
    }

    public function test_it_renders_allowed_core_blocks_and_sanitizes_html(): void
    {
        $html = '<!-- wp:paragraph --><p onclick="alert(1)">Hello<script>alert(1)</script></p><!-- /wp:paragraph -->';

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

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

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('class="wp-block-cover is-light"', $rendered);
        $this->assertStringContainsString('class="wp-block-media-text"', $rendered);
        $this->assertStringContainsString('class="wp-block-gallery has-nested-images columns-default"', $rendered);
        $this->assertStringContainsString('class="wp-block-table"', $rendered);
        $this->assertStringContainsString('class="wp-block-details"', $rendered);
        $this->assertStringContainsString('class="wp-block-video"', $rendered);
    }

    public function test_it_applies_constrained_layout_attributes_to_cover_inner_blocks(): void
    {
        $html = '<!-- wp:cover {"align":"full","layout":{"type":"constrained","contentSize":"640px","wideSize":"980px"}} --><div class="wp-block-cover alignfull"><div class="wp-block-cover__inner-container"><!-- wp:paragraph --><p>Cover title</p><!-- /wp:paragraph --></div></div><!-- /wp:cover -->';

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('class="wp-block-cover alignfull"', $rendered);
        $this->assertStringContainsString('class="wp-block-cover__inner-container is-layout-constrained wp-block-cover-is-layout-constrained"', $rendered);
        $this->assertStringContainsString('style="--wp--style--global--content-size: 640px; --wp--style--global--wide-size: 980px"', $rendered);
        $this->assertStringContainsString('<p>Cover title</p>', $rendered);
    }

    public function test_it_applies_constrained_layout_attributes_to_group_blocks(): void
    {
        $html = '<!-- wp:group {"layout":{"type":"constrained","contentSize":"640px","wideSize":"980px"}} --><div class="wp-block-group"><!-- wp:paragraph --><p>Inner</p><!-- /wp:paragraph --></div><!-- /wp:group -->';

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('class="wp-block-group is-layout-constrained wp-block-group-is-layout-constrained"', $rendered);
        $this->assertStringContainsString('style="--wp--style--global--content-size: 640px; --wp--style--global--wide-size: 980px"', $rendered);
        $this->assertStringContainsString('<p>Inner</p>', $rendered);
    }

    public function test_it_removes_transient_blob_media_urls_from_persisted_markup(): void
    {
        $html = '<!-- wp:cover {"url":"blob:https://site.test/temp"} --><div class="wp-block-cover"><img class="wp-block-cover__image-background" src="blob:https://site.test/temp"><p>Cover</p></div><!-- /wp:cover -->';

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('class="wp-block-cover"', $rendered);
        $this->assertStringContainsString('<p>Cover</p>', $rendered);
        $this->assertStringNotContainsString('blob:', $rendered);
    }

    public function test_it_preserves_safe_gutenberg_inline_styles(): void
    {
        $html = '<!-- wp:paragraph --><p style="margin-top:var(--wp--preset--spacing--40);padding:12px;color:#111;position:absolute;background-image:url(javascript:alert(1))">Styled</p><!-- /wp:paragraph -->';

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('margin-top: var(--wp--preset--spacing--40)', $rendered);
        $this->assertStringContainsString('padding: 12px', $rendered);
        $this->assertStringContainsString('color: #111', $rendered);
        $this->assertStringNotContainsString('position', $rendered);
        $this->assertStringNotContainsString('url(', $rendered);
    }

    public function test_it_preserves_safe_grid_layout_styles(): void
    {
        $html = '<!-- wp:group --><div class="wp-block-group is-layout-grid" style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem;position:absolute"><p>Grid</p></div><!-- /wp:group -->';

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('display: grid', $rendered);
        $this->assertStringContainsString('grid-template-columns: repeat(3,minmax(0,1fr))', $rendered);
        $this->assertStringContainsString('gap: 1rem', $rendered);
        $this->assertStringNotContainsString('position', $rendered);
    }

    public function test_it_strips_dangerous_form_action_urls_from_core_form_markup(): void
    {
        $html = '<!-- wp:form --><form class="wp-block-form" action="javascript:alert(1)"><button formaction="javascript:alert(2)">Send</button></form><!-- /wp:form -->';

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('class="wp-block-form"', $rendered);
        $this->assertStringNotContainsString('javascript:', $rendered);
        $this->assertStringNotContainsString('formaction', $rendered);
        $this->assertStringNotContainsString('action=', $rendered);
    }

    public function test_it_adds_frontend_interaction_metadata_for_core_accordion_and_tabs(): void
    {
        $html = implode("\n", [
            '<!-- wp:accordion {"autoclose":true} --><div class="wp-block-accordion"><!-- wp:accordion-item {"openByDefault":true} --><div class="wp-block-accordion-item is-open"><!-- wp:accordion-heading {"title":"More"} --><h3 class="wp-block-accordion-heading"><button type="button" class="wp-block-accordion-heading__toggle"><span class="wp-block-accordion-heading__toggle-title">More</span></button></h3><!-- /wp:accordion-heading --><!-- wp:accordion-panel --><div class="wp-block-accordion-panel"><!-- wp:paragraph --><p>Panel</p><!-- /wp:paragraph --></div><!-- /wp:accordion-panel --></div><!-- /wp:accordion-item --></div><!-- /wp:accordion -->',
            '<!-- wp:tabs {"activeTabIndex":1} --><div class="wp-block-tabs"><!-- wp:tab-list --><div class="wp-block-tab-list"><!-- wp:tab --><button type="button" role="tab" class="wp-block-tab"></button><!-- /wp:tab --><!-- wp:tab --><button type="button" role="tab" class="wp-block-tab"></button><!-- /wp:tab --></div><!-- /wp:tab-list --><!-- wp:tab-panels --><div class="wp-block-tab-panels"><!-- wp:tab-panel {"label":"First"} --><section class="wp-block-tab-panel"><p>First panel</p></section><!-- /wp:tab-panel --><!-- wp:tab-panel {"label":"Second"} --><section class="wp-block-tab-panel"><p>Second panel</p></section><!-- /wp:tab-panel --></div><!-- /wp:tab-panels --></div><!-- /wp:tabs -->',
        ]);

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('data-sgb-accordion-autoclose="true"', $rendered);
        $this->assertStringContainsString('data-sgb-active-tab-index="1"', $rendered);
        $this->assertStringContainsString('role="tablist"', $rendered);
        $this->assertStringContainsString('data-sgb-tab-label="First"', $rendered);
        $this->assertStringContainsString('data-sgb-tab-label="Second"', $rendered);
    }

    public function test_it_renders_fallback_markup_for_runtime_core_blocks_without_saved_html(): void
    {
        $html = implode('', [
            '<!-- wp:search {"label":"Find","buttonText":"Go","placeholder":"Search site"} /-->',
            '<!-- wp:site-title {"level":2,"isLink":false} /-->',
            '<!-- wp:latest-posts {"postsToShow":3} /-->',
            '<!-- wp:post-title /-->',
        ]);

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('class="wp-block-search sgb-core-fallback-search', $rendered);
        $this->assertStringContainsString('placeholder="Search site"', $rendered);
        $this->assertStringContainsString('>Go</button>', $rendered);
        $this->assertStringContainsString('class="wp-block-site-title"', $rendered);
        $this->assertStringContainsString('data-sgb-core-fallback="core/latest-posts"', $rendered);
        $this->assertStringContainsString('data-sgb-core-fallback="core/post-title"', $rendered);
    }

    public function test_it_renders_nested_fallbacks_inside_runtime_core_container_blocks(): void
    {
        $html = implode('', [
            '<!-- wp:query {"tagName":"section"} --><section class="wp-block-query"><!-- wp:post-template --><ul class="wp-block-post-template"><!-- wp:post-title /--></ul><!-- /wp:post-template --></section><!-- /wp:query -->',
            '<!-- wp:playlist --><figure class="wp-block-playlist"><ol class="wp-block-playlist__tracklist"><!-- wp:playlist-track /--></ol></figure><!-- /wp:playlist -->',
        ]);

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('class="wp-block-query"', $rendered);
        $this->assertStringContainsString('class="wp-block-post-template"', $rendered);
        $this->assertStringContainsString('data-sgb-core-fallback="core/post-title"', $rendered);
        $this->assertStringContainsString('class="wp-block-playlist"', $rendered);
        $this->assertStringContainsString('data-sgb-core-fallback="core/playlist-track"', $rendered);
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

    private function allCoreAllowedOptions(): array
    {
        return [
            'allowed_blocks' => array_merge(CoreBlocks::names(), ['statamic/hero', 'statamic/cta']),
        ];
    }
}
