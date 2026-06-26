<?php

namespace Amazingbv\StatamicGutenberg\Tests;

use Amazingbv\StatamicGutenberg\Blocks\BlockRenderer;
use Amazingbv\StatamicGutenberg\Blocks\BlockRegistry;
use Amazingbv\StatamicGutenberg\Blocks\CoreBlocks;
use Amazingbv\StatamicGutenberg\GutenbergManager;
use Amazingbv\StatamicGutenberg\Patterns\PatternRepository;

class BlockRendererTest extends TestCase
{
    public function test_default_config_allows_only_the_home_block_profile(): void
    {
        $allowed = app(BlockRegistry::class)->allowedBlocks();

        $this->assertGreaterThanOrEqual(121, count(CoreBlocks::names()));
        $this->assertLessThan(count(CoreBlocks::names()), count($allowed));
        $this->assertContains('core/block', $allowed);
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

    public function test_core_block_is_allowed_with_older_published_config(): void
    {
        config(['statamic-gutenberg.allowed_blocks' => ['core/paragraph']]);

        $allowed = app(BlockRegistry::class)->allowedBlocks();

        $this->assertSame(['core/paragraph', 'core/block'], $allowed);
    }

    public function test_it_renders_allowed_core_blocks_and_sanitizes_html(): void
    {
        $html = '<!-- wp:paragraph --><p onclick="alert(1)">Hello<script>alert(1)</script></p><!-- /wp:paragraph -->';

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('<p>Hello</p>', $rendered);
        $this->assertStringNotContainsString('onclick', $rendered);
        $this->assertStringNotContainsString('script', $rendered);
    }

    public function test_it_renders_builtin_statamic_blocks_with_gutenberg_wrapper_attributes(): void
    {
        $html = implode('', [
            '<!-- wp:statamic/hero {"heading":"Audit hero","text":"Intro","buttonText":"Read","buttonUrl":"/read","align":"wide","className":"extra","anchor":"hero-one"} --><section></section><!-- /wp:statamic/hero -->',
            '<!-- wp:statamic/cta {"heading":"Audit CTA","text":"Act now","buttonText":"Contact","buttonUrl":"/contact","align":"full","className":"cta-extra","anchor":"cta-one"} --><section></section><!-- /wp:statamic/cta -->',
        ]);

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('class="wp-block-statamic-hero alignwide extra sgb-custom-block sgb-custom-block--hero"', $rendered);
        $this->assertStringContainsString('id="hero-one"', $rendered);
        $this->assertStringContainsString('<a class="wp-block-button__link" href="/read">Read</a>', $rendered);
        $this->assertStringContainsString('class="wp-block-statamic-cta alignfull cta-extra sgb-custom-block sgb-custom-block--cta"', $rendered);
        $this->assertStringContainsString('id="cta-one"', $rendered);
        $this->assertStringContainsString('<a class="wp-block-button__link" href="/contact">Contact</a>', $rendered);
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

    public function test_it_preserves_wrapper_attributes_on_constructed_youtube_embeds(): void
    {
        $html = '<!-- wp:embed {"url":"https://www.youtube.com/watch?v=tCDvOQI3pco","type":"video","providerNameSlug":"youtube","responsive":true,"align":"wide","className":"audit-embed","anchor":"youtube-one"} --><figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube"><div class="wp-block-embed__wrapper">https://www.youtube.com/watch?v=tCDvOQI3pco</div></figure><!-- /wp:embed -->';

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('id="youtube-one"', $rendered);
        $this->assertStringContainsString('class="wp-block-embed alignwide audit-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio"', $rendered);
        $this->assertStringContainsString('src="https://www.youtube.com/embed/tCDvOQI3pco"', $rendered);
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

    public function test_it_renders_dynamic_inner_blocks_inside_static_cover_and_media_text_markup(): void
    {
        app(GutenbergManager::class)->block('statamic/badge', function ($block) {
            return '<strong>Rendered '.e($block->attribute('label')).'</strong>';
        });

        $html = implode('', [
            '<!-- wp:cover --><div class="wp-block-cover"><div class="wp-block-cover__inner-container"><!-- wp:statamic/badge {"label":"cover"} --><em>Saved cover fallback</em><!-- /wp:statamic/badge --></div></div><!-- /wp:cover -->',
            '<!-- wp:media-text --><div class="wp-block-media-text"><figure class="wp-block-media-text__media"><img src="/storage/media.jpg" alt=""></figure><div class="wp-block-media-text__content"><!-- wp:statamic/badge {"label":"media"} --><em>Saved media fallback</em><!-- /wp:statamic/badge --></div></div><!-- /wp:media-text -->',
        ]);

        $rendered = (string) app(BlockRenderer::class)->render($html, [
            'allowed_blocks' => ['core/cover', 'core/media-text', 'statamic/badge'],
        ]);

        $this->assertStringContainsString('class="wp-block-cover"', $rendered);
        $this->assertStringContainsString('class="wp-block-cover__inner-container"', $rendered);
        $this->assertStringContainsString('class="wp-block-media-text__content"', $rendered);
        $this->assertStringContainsString('<strong>Rendered cover</strong>', $rendered);
        $this->assertStringContainsString('<strong>Rendered media</strong>', $rendered);
        $this->assertStringNotContainsString('Saved cover fallback', $rendered);
        $this->assertStringNotContainsString('Saved media fallback', $rendered);
    }

    public function test_it_renders_gallery_inner_images_through_the_image_renderer(): void
    {
        $html = '<!-- wp:gallery --><figure class="wp-block-gallery has-nested-images"><!-- wp:image {"lightbox":{"enabled":true}} --><figure class="wp-block-image"><img src="/storage/a.jpg" alt="A"></figure><!-- /wp:image --><figcaption class="blocks-gallery-caption">Gallery caption</figcaption></figure><!-- /wp:gallery -->';

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('class="wp-block-gallery has-nested-images"', $rendered);
        $this->assertStringContainsString('wp-lightbox-container', $rendered);
        $this->assertStringContainsString('data-sgb-lightbox="true"', $rendered);
        $this->assertStringContainsString('<figcaption class="blocks-gallery-caption">Gallery caption</figcaption>', $rendered);
    }

    public function test_it_preserves_safe_file_pdf_preview_objects(): void
    {
        $html = '<!-- wp:file {"href":"https://site.test/storage/test.pdf","displayPreview":true} --><div class="wp-block-file"><object class="wp-block-file__embed" data="https://site.test/storage/test.pdf" type="application/pdf" style="width:100%;height:420px" aria-label="test.pdf"></object><a href="https://site.test/storage/test.pdf">test.pdf</a></div><!-- /wp:file -->';

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('<object class="wp-block-file__embed" data="https://site.test/storage/test.pdf" type="application/pdf"', $rendered);
        $this->assertStringContainsString('style="width: 100%; height: 420px"', $rendered);
        $this->assertStringContainsString('<a href="https://site.test/storage/test.pdf">test.pdf</a>', $rendered);
    }

    public function test_it_removes_unsafe_file_preview_objects(): void
    {
        $html = '<!-- wp:file {"href":"https://site.test/storage/test.pdf","displayPreview":true} --><div class="wp-block-file"><object class="wp-block-file__embed" data="javascript:alert(1)" type="application/pdf"></object><object class="wp-block-file__embed" data="https://site.test/storage/test.html" type="text/html"></object><a href="https://site.test/storage/test.pdf">test.pdf</a></div><!-- /wp:file -->';

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringNotContainsString('<object', $rendered);
        $this->assertStringNotContainsString('javascript:', $rendered);
        $this->assertStringContainsString('<a href="https://site.test/storage/test.pdf">test.pdf</a>', $rendered);
    }

    public function test_it_applies_constrained_layout_attributes_to_group_blocks(): void
    {
        $html = '<!-- wp:group {"layout":{"type":"constrained","contentSize":"640px","wideSize":"980px"}} --><div class="wp-block-group"><!-- wp:paragraph --><p>Inner</p><!-- /wp:paragraph --></div><!-- /wp:group -->';

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('class="wp-block-group is-layout-constrained wp-block-group-is-layout-constrained"', $rendered);
        $this->assertStringContainsString('style="--wp--style--global--content-size: 640px; --wp--style--global--wide-size: 980px"', $rendered);
        $this->assertStringContainsString('<p>Inner</p>', $rendered);
    }

    public function test_it_matches_editor_grid_layout_minimum_column_width(): void
    {
        $html = '<!-- wp:group {"layout":{"type":"grid","minimumColumnWidth":"14rem"}} --><div class="wp-block-group"><!-- wp:paragraph --><p>Grid</p><!-- /wp:paragraph --></div><!-- /wp:group -->';

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('class="wp-block-group is-layout-grid wp-block-group-is-layout-grid"', $rendered);
        $this->assertStringContainsString('grid-template-columns: repeat(auto-fill, minmax(min(14rem, 100%), 1fr))', $rendered);
        $this->assertStringContainsString('<p>Grid</p>', $rendered);
    }

    public function test_it_removes_transient_blob_media_urls_from_persisted_markup(): void
    {
        $html = '<!-- wp:cover {"url":"blob:https://site.test/temp"} --><div class="wp-block-cover"><img class="wp-block-cover__image-background" src="blob:https://site.test/temp"><p>Cover</p></div><!-- /wp:cover -->';

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('class="wp-block-cover"', $rendered);
        $this->assertStringContainsString('<p>Cover</p>', $rendered);
        $this->assertStringNotContainsString('blob:', $rendered);
    }

    public function test_it_preserves_safe_cover_parallax_background_images(): void
    {
        $html = '<!-- wp:cover {"url":"https://site.test/storage/cover.jpg","hasParallax":true} --><div class="wp-block-cover has-parallax"><div class="wp-block-cover__image-background has-parallax" style="background-position:50% 50%;background-image:url(https://site.test/storage/cover.jpg)"></div><span aria-hidden="true" class="wp-block-cover__background has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:paragraph --><p>Cover</p><!-- /wp:paragraph --></div></div><!-- /wp:cover -->';

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('class="wp-block-cover has-parallax"', $rendered);
        $this->assertStringContainsString('class="wp-block-cover__image-background has-parallax"', $rendered);
        $this->assertStringContainsString('background-image: url(https://site.test/storage/cover.jpg)', $rendered);
        $this->assertStringContainsString('<p>Cover</p>', $rendered);
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

    public function test_it_preserves_text_alignment_and_color_markup(): void
    {
        $html = implode('', [
            '<!-- wp:paragraph {"style":{"typography":{"textAlign":"justify"}},"textColor":"blue"} --><p class="has-text-align-justify has-blue-color has-text-color" style="text-align:justify">Paragraph</p><!-- /wp:paragraph -->',
            '<!-- wp:heading {"style":{"typography":{"textAlign":"right"}},"textColor":"red"} --><h2 class="has-text-align-right has-red-color has-text-color" style="text-align:right">Heading</h2><!-- /wp:heading -->',
        ]);

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('class="has-text-align-justify has-blue-color has-text-color"', $rendered);
        $this->assertStringContainsString('style="text-align: justify"', $rendered);
        $this->assertStringContainsString('has-text-align-right', $rendered);
        $this->assertStringContainsString('has-red-color', $rendered);
        $this->assertStringContainsString('wp-block-heading', $rendered);
        $this->assertStringContainsString('style="text-align: right"', $rendered);
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

    public function test_it_renders_synced_patterns_from_core_block_references(): void
    {
        $this->bindPatternRepository([
            123 => '<!-- wp:paragraph --><p>Synced pattern</p><!-- /wp:paragraph -->',
        ]);

        $html = '<!-- wp:block {"ref":123} /-->';
        $rendered = (string) app(BlockRenderer::class)->render($html, [
            'allowed_blocks' => ['core/paragraph'],
        ]);

        $this->assertSame('<p>Synced pattern</p>', $rendered);
    }

    public function test_it_prevents_recursive_synced_pattern_rendering(): void
    {
        $this->bindPatternRepository([
            123 => '<!-- wp:block {"ref":123} /-->',
        ]);

        $html = '<!-- wp:block {"ref":123} /-->';
        $rendered = (string) app(BlockRenderer::class)->render($html, [
            'allowed_blocks' => ['core/paragraph'],
        ]);

        $this->assertSame('', $rendered);
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

    private function bindPatternRepository(array $patterns): void
    {
        $this->app->instance(PatternRepository::class, new class($patterns) extends PatternRepository
        {
            public function __construct(private array $patterns)
            {
                //
            }

            public function findRenderablePattern(int|string|null $id): ?array
            {
                return isset($this->patterns[(int) $id])
                    ? ['id' => (int) $id, 'content' => $this->patterns[(int) $id]]
                    : null;
            }
        });

        $this->app->forgetInstance(BlockRenderer::class);
    }
}
