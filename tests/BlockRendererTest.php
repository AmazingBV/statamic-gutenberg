<?php

namespace Amazingbv\StatamicGutenberg\Tests;

use Amazingbv\StatamicGutenberg\Blocks\BlockRenderer;
use Amazingbv\StatamicGutenberg\Blocks\BlockRegistry;
use Amazingbv\StatamicGutenberg\Blocks\CoreBlocks;
use Amazingbv\StatamicGutenberg\GutenbergManager;
use Amazingbv\StatamicGutenberg\Icons\IconRepository;
use Amazingbv\StatamicGutenberg\Patterns\PatternRepository;
use Statamic\Contracts\Assets\AssetRepository as AssetRepositoryContract;
use Statamic\Facades\Asset as AssetFacade;

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
        $this->assertStringContainsString('<a class="wp-block-button__link wp-element-button" href="/read">Read</a>', $rendered);
        $this->assertStringContainsString('class="wp-block-statamic-cta alignfull cta-extra sgb-custom-block sgb-custom-block--cta"', $rendered);
        $this->assertStringContainsString('id="cta-one"', $rendered);
        $this->assertStringContainsString('<a class="wp-block-button__link wp-element-button" href="/contact">Contact</a>', $rendered);
    }

    public function test_it_renders_static_markup_for_common_standard_core_blocks(): void
    {
        $html = implode('', [
            '<!-- wp:cover {"dimRatio":50,"customOverlayColor":"#fff","layout":{"type":"constrained"}} --><div class="wp-block-cover is-light"><span aria-hidden="true" class="wp-block-cover__background has-background-dim" style="background-color:#fff"></span><div class="wp-block-cover__inner-container"><!-- wp:paragraph --><p>Cover title</p><!-- /wp:paragraph --></div></div><!-- /wp:cover -->',
            '<!-- wp:media-text --><div class="wp-block-media-text"><figure class="wp-block-media-text__media"><img src="/storage/media.jpg" alt=""></figure><div class="wp-block-media-text__content"><!-- wp:paragraph --><p>Media text</p><!-- /wp:paragraph --></div></div><!-- /wp:media-text -->',
            '<!-- wp:gallery --><figure class="wp-block-gallery has-nested-images columns-default"><!-- wp:image --><figure class="wp-block-image"><img src="/storage/a.jpg" alt=""></figure><!-- /wp:image --></figure><!-- /wp:gallery -->',
            '<!-- wp:table --><figure class="wp-block-table"><table><tbody><tr><td>A</td></tr></tbody></table></figure><!-- /wp:table -->',
            '<!-- wp:details --><details class="wp-block-details"><summary>More</summary><p>Details</p></details><!-- /wp:details -->',
            '<!-- wp:video --><figure class="wp-block-video"><video src="/storage/movie.mp4"></video></figure><!-- /wp:video -->',
        ]);

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('class="wp-block-cover is-light"', $rendered);
        $this->assertStringContainsString('class="wp-block-media-text"', $rendered);
        $this->assertStringContainsString('class="wp-block-gallery has-nested-images columns-default"', $rendered);
        $this->assertStringContainsString('class="wp-block-table"', $rendered);
        $this->assertStringContainsString('class="wp-block-details"', $rendered);
        $this->assertStringContainsString('class="wp-block-video"', $rendered);
        $this->assertStringContainsString('controls="controls"', $rendered);
    }

    public function test_it_respects_disabled_video_controls(): void
    {
        $html = '<!-- wp:video {"controls":false} --><figure class="wp-block-video"><video controls src="/storage/movie.mp4"></video></figure><!-- /wp:video -->';

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('class="wp-block-video"', $rendered);
        $this->assertStringNotContainsString('controls="controls"', $rendered);
    }

    public function test_it_constructs_audio_blocks_from_attributes(): void
    {
        $html = '<!-- wp:audio {"src":"/storage/podcast.mp3","caption":"Episode <script>alert(1)</script>","autoplay":true,"loop":true,"preload":"metadata","align":"wide","anchor":"audio-one"} /-->';

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('class="wp-block-audio alignwide"', $rendered);
        $this->assertStringContainsString('id="audio-one"', $rendered);
        $this->assertStringContainsString('<audio controls="controls" src="/storage/podcast.mp3" autoplay="autoplay" loop="loop" preload="metadata"></audio>', $rendered);
        $this->assertStringContainsString('<figcaption class="wp-element-caption">Episode &lt;script&gt;alert(1)&lt;/script&gt;</figcaption>', $rendered);
        $this->assertStringNotContainsString('<script>', $rendered);
    }

    public function test_it_constructs_video_blocks_from_attributes(): void
    {
        $html = '<!-- wp:video {"src":"/storage/movie.mp4","caption":"Video <script>alert(1)</script>","poster":"/storage/poster.jpg","autoplay":true,"loop":true,"muted":true,"playsInline":true,"preload":"auto","tracks":[{"src":"/storage/captions.vtt","kind":"captions","srcLang":"nl-NL","label":"Nederlands","default":true},{"src":"javascript:alert(1)","kind":"bad","srcLang":"bad lang","label":"Bad"}],"align":"wide","anchor":"video-one"} /-->';

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('class="wp-block-video alignwide"', $rendered);
        $this->assertStringContainsString('id="video-one"', $rendered);
        $this->assertStringContainsString('<video controls="controls" autoplay="autoplay" loop="loop" muted="muted" poster="/storage/poster.jpg" preload="auto" src="/storage/movie.mp4" playsinline="playsinline">', $rendered);
        $this->assertStringContainsString('<track src="/storage/captions.vtt" kind="captions" srclang="nl-NL" label="Nederlands" default="default">', $rendered);
        $this->assertStringContainsString('<figcaption class="wp-element-caption">Video &lt;script&gt;alert(1)&lt;/script&gt;</figcaption>', $rendered);
        $this->assertStringNotContainsString('javascript:', $rendered);
        $this->assertStringNotContainsString('<script>', $rendered);
    }

    public function test_it_constructs_spacer_blocks_from_attributes(): void
    {
        $html = '<!-- wp:spacer {"height":"var:preset|spacing|50","width":"20px","align":"wide","anchor":"spacer-one"} /-->';

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('<div', $rendered);
        $this->assertStringContainsString('class="wp-block-spacer alignwide"', $rendered);
        $this->assertStringContainsString('id="spacer-one"', $rendered);
        $this->assertStringContainsString('aria-hidden="true"', $rendered);
        $this->assertStringContainsString('style="height: var(--wp--preset--spacing--50); width: 20px"', $rendered);
    }

    public function test_it_constructs_separator_blocks_from_attributes(): void
    {
        $html = '<!-- wp:separator {"tagName":"div","opacity":"css","backgroundColor":"blue","align":"wide","anchor":"separator-one","className":"separator-extra","style":{"spacing":{"margin":{"top":"2rem","bottom":"var:preset|spacing|40"}}}} /-->';

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringStartsWith('<div', $rendered);
        $this->assertStringContainsString('id="separator-one"', $rendered);
        $this->assertStringContainsString('class="wp-block-separator alignwide separator-extra has-css-opacity has-blue-color has-text-color"', $rendered);
        $this->assertStringContainsString('style="margin-top: 2rem; margin-bottom: var(--wp--preset--spacing--40)"', $rendered);
        $this->assertStringNotContainsString('has-blue-background-color', $rendered);
    }

    public function test_it_constructs_math_blocks_from_attributes(): void
    {
        $html = '<!-- wp:math {"latex":"x^2=5","mathML":"<mrow><msup><mi>x</mi><mn>2</mn></msup><mo>=</mo><mn>5</mn></mrow><script>alert(1)</script>"} /-->';

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('class="wp-block-math"', $rendered);
        $this->assertStringContainsString('<math display="block"><mrow><msup><mi>x</mi><mn>2</mn></msup><mo>=</mo><mn>5</mn></mrow></math>', $rendered);
        $this->assertStringNotContainsString('<script>', $rendered);
    }

    public function test_it_preserves_saved_math_markup(): void
    {
        $html = '<!-- wp:math {"latex":"x"} --><div class="wp-block-math"><math display="block"><mrow><mi>x</mi></mrow></math></div><!-- /wp:math -->';

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertSame('<div class="wp-block-math"><math display="block"><mrow><mi>x</mi></mrow></math></div>', $rendered);
    }

    public function test_it_constructs_file_blocks_from_attributes(): void
    {
        $html = '<!-- wp:file {"href":"https://site.test/storage/test.pdf","fileName":"test.pdf","textLinkHref":"https://site.test/download/test.pdf","textLinkTarget":"_blank","showDownloadButton":true,"downloadButtonText":"Download file","displayPreview":true,"previewHeight":420,"align":"wide","anchor":"file-one"} /-->';

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('class="wp-block-file alignwide"', $rendered);
        $this->assertStringContainsString('id="file-one"', $rendered);
        $this->assertStringContainsString('<object class="wp-block-file__embed" data="https://site.test/storage/test.pdf" type="application/pdf" style="width: 100%; height: 420px" aria-label="test.pdf"></object>', $rendered);
        $this->assertStringContainsString('<a id="wp-block-file--media-', $rendered);
        $this->assertStringContainsString('href="https://site.test/download/test.pdf" target="_blank" rel="noreferrer noopener">test.pdf</a>', $rendered);
        $this->assertStringContainsString('class="wp-block-file__button wp-element-button" download="download"', $rendered);
        $this->assertStringContainsString('>Download file</a>', $rendered);
    }

    public function test_it_constructs_media_text_blocks_from_attributes_and_inner_blocks(): void
    {
        $html = '<!-- wp:media-text {"mediaUrl":"https://site.test/storage/media.jpg","mediaType":"image","mediaAlt":"Media alt","mediaId":42,"mediaSizeSlug":"large","mediaPosition":"right","mediaWidth":35,"verticalAlignment":"center","isStackedOnMobile":true,"imageFill":true,"focalPoint":{"x":0.25,"y":0.75},"href":"https://site.test/media","linkTarget":"_blank","rel":"noreferrer noopener","linkClass":"media-link","align":"wide","anchor":"media-text-one"} --><!-- wp:paragraph --><p>Media text</p><!-- /wp:paragraph --><!-- /wp:media-text -->';

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('id="media-text-one"', $rendered);
        $this->assertStringContainsString('class="wp-block-media-text alignwide has-media-on-the-right is-stacked-on-mobile is-vertically-aligned-center is-image-fill-element"', $rendered);
        $this->assertStringContainsString('style="grid-template-columns: auto 35%"', $rendered);
        $this->assertStringContainsString('<div class="wp-block-media-text__content"><p>Media text</p></div>', $rendered);
        $this->assertStringContainsString('<figure class="wp-block-media-text__media"><a class="media-link" href="https://site.test/media" target="_blank" rel="noreferrer noopener"><img src="https://site.test/storage/media.jpg" alt="Media alt" class="wp-image-42 size-large" style="object-position: 25% 75%"></a></figure>', $rendered);
    }

    public function test_it_constructs_details_blocks_from_attributes_and_inner_blocks(): void
    {
        $html = '<!-- wp:details {"summary":"More <strong>info</strong><script>alert(1)</script>","showContent":true,"name":"faq","align":"wide","anchor":"details-one"} --><!-- wp:paragraph --><p>Hidden answer</p><!-- /wp:paragraph --><!-- /wp:details -->';

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('<details', $rendered);
        $this->assertStringContainsString('id="details-one"', $rendered);
        $this->assertStringContainsString('class="wp-block-details alignwide"', $rendered);
        $this->assertStringContainsString('name="faq"', $rendered);
        $this->assertStringContainsString('open="open"', $rendered);
        $this->assertStringContainsString('<summary>More <strong>info</strong></summary>', $rendered);
        $this->assertStringContainsString('<p>Hidden answer</p>', $rendered);
        $this->assertStringNotContainsString('<script>', $rendered);
    }

    public function test_it_renders_duotone_filters_for_image_and_cover_blocks(): void
    {
        $imageDuotone = ['#000000', '#ffffff'];
        $coverDuotone = ['#123456', '#abcdef'];
        $imageFilterId = 'wp-duotone-block-'.substr(md5('core/image'.serialize($imageDuotone)), 0, 12);
        $coverFilterId = 'wp-duotone-block-'.substr(md5('core/cover'.serialize($coverDuotone)), 0, 12);

        $html = implode('', [
            '<!-- wp:image {"style":{"color":{"duotone":["#000000","#ffffff"]}}} --><figure class="wp-block-image"><img src="/storage/a.jpg" alt=""></figure><!-- /wp:image -->',
            '<!-- wp:cover {"style":{"color":{"duotone":["#123456","#abcdef"]}}} --><div class="wp-block-cover"><img class="wp-block-cover__image-background" src="/storage/b.jpg" alt=""><div class="wp-block-cover__inner-container"><p>Cover title</p></div></div><!-- /wp:cover -->',
        ]);

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('<filter id="'.$imageFilterId.'"', $rendered);
        $this->assertStringContainsString('<filter id="'.$coverFilterId.'"', $rendered);
        $this->assertStringContainsString('filter: url(#'.$imageFilterId.')', $rendered);
        $this->assertStringContainsString('filter: url(#'.$coverFilterId.')', $rendered);
        $this->assertStringContainsString('class="wp-block-image wp-duotone-block-', $rendered);
        $this->assertStringContainsString('class="wp-block-cover wp-duotone-block-', $rendered);
        $this->assertStringContainsString('data-statamic-gutenberg-duotone', $rendered);
    }

    public function test_it_preserves_wrapper_attributes_on_constructed_youtube_embeds(): void
    {
        $html = '<!-- wp:embed {"url":"https://www.youtube.com/watch?v=tCDvOQI3pco","type":"video","providerNameSlug":"youtube","responsive":true,"align":"wide","className":"audit-embed","anchor":"youtube-one"} --><figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube saved-class" data-saved="yes"><div class="wp-block-embed__wrapper">https://www.youtube.com/watch?v=tCDvOQI3pco</div><figcaption>Saved caption</figcaption></figure><!-- /wp:embed -->';

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('id="youtube-one"', $rendered);
        $this->assertStringContainsString('data-saved="yes"', $rendered);
        $this->assertStringContainsString('saved-class', $rendered);
        $this->assertStringContainsString('alignwide', $rendered);
        $this->assertStringContainsString('audit-embed', $rendered);
        $this->assertStringContainsString('wp-embed-aspect-16-9', $rendered);
        $this->assertStringContainsString('wp-has-aspect-ratio', $rendered);
        $this->assertStringContainsString('src="https://www.youtube.com/embed/tCDvOQI3pco"', $rendered);
        $this->assertStringContainsString('<figcaption>Saved caption</figcaption>', $rendered);
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

    public function test_it_renders_dynamic_inner_blocks_inside_static_core_container_markup(): void
    {
        app(GutenbergManager::class)->block('statamic/badge', function ($block) {
            return '<strong>Rendered '.e($block->attribute('label')).'</strong>';
        });

        $html = implode('', [
            '<!-- wp:details --><details class="wp-block-details"><summary>More</summary><!-- wp:statamic/badge {"label":"details"} --><em>Saved details fallback</em><!-- /wp:statamic/badge --></details><!-- /wp:details -->',
            '<!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:statamic/badge {"label":"quote"} --><em>Saved quote fallback</em><!-- /wp:statamic/badge --><cite>Source</cite></blockquote><!-- /wp:quote -->',
            '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li><!-- wp:statamic/badge {"label":"list"} --><em>Saved list fallback</em><!-- /wp:statamic/badge --></li><!-- /wp:list-item --></ul><!-- /wp:list -->',
        ]);

        $rendered = (string) app(BlockRenderer::class)->render($html, [
            'allowed_blocks' => ['core/details', 'core/quote', 'core/list', 'core/list-item', 'statamic/badge'],
        ]);

        $this->assertStringContainsString('class="wp-block-details"', $rendered);
        $this->assertStringContainsString('<summary>More</summary>', $rendered);
        $this->assertStringContainsString('class="wp-block-quote"', $rendered);
        $this->assertStringContainsString('<cite>Source</cite>', $rendered);
        $this->assertStringContainsString('class="wp-block-list"', $rendered);
        $this->assertStringContainsString('<strong>Rendered details</strong>', $rendered);
        $this->assertStringContainsString('<strong>Rendered quote</strong>', $rendered);
        $this->assertStringContainsString('<strong>Rendered list</strong>', $rendered);
        $this->assertStringNotContainsString('Saved details fallback', $rendered);
        $this->assertStringNotContainsString('Saved quote fallback', $rendered);
        $this->assertStringNotContainsString('Saved list fallback', $rendered);
    }

    public function test_it_renders_gallery_inner_images_through_the_image_renderer(): void
    {
        $html = '<!-- wp:gallery {"style":{"spacing":{"blockGap":"var:preset|spacing|50"}}} --><figure class="wp-block-gallery has-nested-images"><!-- wp:image {"lightbox":{"enabled":true}} --><figure class="wp-block-image"><img src="/storage/a.jpg" alt="A"></figure><!-- /wp:image --><figcaption class="blocks-gallery-caption">Gallery caption</figcaption></figure><!-- /wp:gallery -->';

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('class="wp-block-gallery has-nested-images"', $rendered);
        $this->assertStringContainsString('--wp--style--unstable-gallery-gap: var(--wp--preset--spacing--50)', $rendered);
        $this->assertStringContainsString('gap: var(--wp--preset--spacing--50)', $rendered);
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

    public function test_it_ignores_unsafe_grid_layout_minimum_column_width(): void
    {
        $html = '<!-- wp:group {"layout":{"type":"grid","minimumColumnWidth":"14rem);background-image:url(javascript:alert(1))"}} --><div class="wp-block-group"><!-- wp:paragraph --><p>Grid</p><!-- /wp:paragraph --></div><!-- /wp:group -->';

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('class="wp-block-group is-layout-grid wp-block-group-is-layout-grid"', $rendered);
        $this->assertStringNotContainsString('grid-template-columns:', $rendered);
        $this->assertStringNotContainsString('javascript:', $rendered);
        $this->assertStringNotContainsString('background-image', $rendered);
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
        $html = implode('', [
            '<!-- wp:paragraph --><p style="margin-top:var(--wp--preset--spacing--40);padding:12px;color:#111;position:absolute;background-image:url(javascript:alert(1))">Styled</p><!-- /wp:paragraph -->',
            '<!-- wp:button --><div class="wp-block-button" style="--wp--block-button--width:50%;width:var(--wp--block-button--width)"><a class="wp-block-button__link wp-element-button">Button</a></div><!-- /wp:button -->',
        ]);

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('margin-top: var(--wp--preset--spacing--40)', $rendered);
        $this->assertStringContainsString('padding: 12px', $rendered);
        $this->assertStringContainsString('color: #111', $rendered);
        $this->assertStringContainsString('--wp--block-button--width: 50%', $rendered);
        $this->assertStringContainsString('width: var(--wp--block-button--width)', $rendered);
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

    public function test_it_renders_wordpress_compatible_search_fallback_variants(): void
    {
        $html = implode('', [
            '<!-- wp:search {"label":"Find","buttonText":"Go","buttonPosition":"button-only","buttonUseIcon":true,"showLabel":false,"width":80,"widthUnit":"%","query":{"post_type":"page","paged":2,"featured":true}} /-->',
            '<!-- wp:search {"buttonText":"Hidden","buttonPosition":"no-button"} /-->',
        ]);

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('wp-block-search__button-only', $rendered);
        $this->assertStringContainsString('wp-block-search__searchfield-hidden', $rendered);
        $this->assertStringContainsString('wp-block-search__icon-button', $rendered);
        $this->assertStringContainsString('class="wp-block-search__label sgb-screen-reader-text"', $rendered);
        $this->assertStringContainsString('style="width: 80%"', $rendered);
        $this->assertStringContainsString('name="post_type" value="page"', $rendered);
        $this->assertStringContainsString('name="paged" value="2"', $rendered);
        $this->assertStringContainsString('name="featured" value="1"', $rendered);
        $this->assertStringContainsString('aria-label="Go"', $rendered);
        $this->assertStringContainsString('<svg class="search-icon"', $rendered);
        $this->assertStringContainsString('wp-block-search__no-button', $rendered);
        $this->assertStringNotContainsString('wp-block-search__button-button-only', $rendered);
        $this->assertStringNotContainsString('>Hidden</button>', $rendered);
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

    public function test_runtime_core_fallbacks_preserve_wrapper_attributes(): void
    {
        $html = implode('', [
            '<!-- wp:search {"align":"wide","anchor":"search-one","className":"search-extra","backgroundColor":"blue","style":{"spacing":{"margin":{"top":"2rem"}}}} /-->',
            '<!-- wp:site-title {"align":"center","anchor":"title-one","className":"title-extra","textColor":"red"} /-->',
            '<!-- wp:read-more {"align":"right","anchor":"read-more-one","className":"read-extra","content":"Continue","linkTarget":"_blank"} /-->',
        ]);

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('id="search-one"', $rendered);
        $this->assertStringContainsString('class="wp-block-search alignwide search-extra has-blue-background-color has-background sgb-core-fallback-search', $rendered);
        $this->assertStringContainsString('style="margin-top: 2rem"', $rendered);
        $this->assertStringContainsString('id="title-one"', $rendered);
        $this->assertStringContainsString('class="wp-block-site-title aligncenter title-extra has-red-color has-text-color"', $rendered);
        $this->assertStringContainsString('id="read-more-one"', $rendered);
        $this->assertStringContainsString('class="wp-block-read-more alignright read-extra"', $rendered);
        $this->assertStringContainsString('target="_blank"', $rendered);
        $this->assertStringContainsString('>Continue</a>', $rendered);
    }

    public function test_it_preserves_more_and_nextpage_markers(): void
    {
        $html = implode('', [
            '<!-- wp:more {"customText":"Read more audit","noTeaser":true} --><!--more Read more audit--><!--noteaser--><!-- /wp:more -->',
            '<!-- wp:nextpage --><!--nextpage--><!-- /wp:nextpage -->',
        ]);

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('<!--more Read more audit-->', $rendered);
        $this->assertStringContainsString('<!--noteaser-->', $rendered);
        $this->assertStringContainsString('<!--nextpage-->', $rendered);
    }

    public function test_it_preserves_wrapper_block_attributes(): void
    {
        $html = '<!-- wp:group --><div class="wp-block-group alignwide has-background" style="padding-top:var(--wp--preset--spacing--50)"><!-- wp:paragraph --><p>Inner</p><!-- /wp:paragraph --></div><!-- /wp:group -->';

        $rendered = (string) app(BlockRenderer::class)->render($html);

        $this->assertStringContainsString('class="wp-block-group alignwide has-background"', $rendered);
        $this->assertStringContainsString('style="padding-top: var(--wp--preset--spacing--50)"', $rendered);
        $this->assertStringContainsString('<p>Inner</p>', $rendered);
    }

    public function test_it_deduplicates_wrapper_support_styles_against_saved_markup(): void
    {
        $html = '<!-- wp:group {"style":{"spacing":{"padding":{"top":"2rem","bottom":"3rem"}},"background":{"backgroundImage":{"url":"https://site.test/bg.jpg"},"backgroundSize":"cover"}},"layout":{"type":"grid","columnCount":3}} --><div class="wp-block-group" style="padding-top:1rem;padding-bottom:3rem;background-image:url(https://site.test/bg.jpg);background-size:contain;display:block;grid-template-columns:repeat(1,minmax(0,1fr))"><!-- wp:paragraph --><p>Inner</p><!-- /wp:paragraph --></div><!-- /wp:group -->';

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertSame(1, substr_count($rendered, 'padding-top:'));
        $this->assertSame(1, substr_count($rendered, 'padding-bottom:'));
        $this->assertSame(1, substr_count($rendered, 'background-image:'));
        $this->assertSame(1, substr_count($rendered, 'background-size:'));
        $this->assertSame(1, substr_count($rendered, 'display:'));
        $this->assertSame(1, substr_count($rendered, 'grid-template-columns:'));
        $this->assertStringContainsString('padding-top: 2rem', $rendered);
        $this->assertStringContainsString('background-size: cover', $rendered);
        $this->assertStringContainsString('display: grid', $rendered);
        $this->assertStringContainsString('grid-template-columns: repeat(3, minmax(0, 1fr))', $rendered);
    }

    public function test_it_applies_wrapper_supports_to_wrapperless_container_blocks(): void
    {
        $html = implode('', [
            '<!-- wp:group {"align":"wide","anchor":"group-one","className":"extra-group","backgroundColor":"blue","style":{"spacing":{"padding":{"top":"1rem","bottom":"2rem"}},"border":{"radius":"12px"}},"layout":{"type":"constrained","contentSize":"640px"}} -->',
            '<!-- wp:paragraph --><p>Inner</p><!-- /wp:paragraph -->',
            '<!-- /wp:group -->',
            '<!-- wp:columns {"align":"wide","anchor":"columns-one","style":{"spacing":{"margin":{"top":"2rem"}}}} -->',
            '<!-- wp:column --><div class="wp-block-column"><p>Column</p></div><!-- /wp:column -->',
            '<!-- /wp:columns -->',
            '<!-- wp:buttons {"align":"wide","anchor":"buttons-one","style":{"spacing":{"blockGap":"1rem"}}} -->',
            '<!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Go</a></div><!-- /wp:button -->',
            '<!-- /wp:buttons -->',
        ]);

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('class="wp-block-group alignwide extra-group has-blue-background-color has-background is-layout-constrained wp-block-group-is-layout-constrained"', $rendered);
        $this->assertStringContainsString('id="group-one"', $rendered);
        $this->assertStringContainsString('padding-top: 1rem', $rendered);
        $this->assertStringContainsString('padding-bottom: 2rem', $rendered);
        $this->assertStringContainsString('border-radius: 12px', $rendered);
        $this->assertStringContainsString('--wp--style--global--content-size: 640px', $rendered);
        $this->assertStringContainsString('class="wp-block-columns alignwide"', $rendered);
        $this->assertStringContainsString('id="columns-one"', $rendered);
        $this->assertStringContainsString('margin-top: 2rem', $rendered);
        $this->assertStringContainsString('class="wp-block-buttons alignwide"', $rendered);
        $this->assertStringContainsString('id="buttons-one"', $rendered);
        $this->assertStringContainsString('--wp--style--block-gap: 1rem', $rendered);
        $this->assertStringNotContainsString('class="wp-block-button wp-block-buttons', $rendered);
        $this->assertStringNotContainsString('class="wp-block-column wp-block-columns', $rendered);
    }

    public function test_it_applies_background_image_supports_to_wrapper_blocks(): void
    {
        $html = implode('', [
            '<!-- wp:group {"style":{"background":{"backgroundImage":{"url":"https://site.test/storage/bg.jpg"},"backgroundSize":"cover","backgroundPosition":"center center","backgroundRepeat":"no-repeat"}}} -->',
            '<!-- wp:paragraph --><p>Safe</p><!-- /wp:paragraph -->',
            '<!-- /wp:group -->',
            '<!-- wp:group {"style":{"background":{"backgroundImage":{"url":"javascript:alert(1)"},"backgroundSize":"contain"}}} -->',
            '<!-- wp:paragraph --><p>Unsafe</p><!-- /wp:paragraph -->',
            '<!-- /wp:group -->',
        ]);

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('background-image: url(https://site.test/storage/bg.jpg)', $rendered);
        $this->assertStringContainsString('background-size: cover', $rendered);
        $this->assertStringContainsString('background-position: center center', $rendered);
        $this->assertStringContainsString('background-repeat: no-repeat', $rendered);
        $this->assertStringContainsString('background-size: contain', $rendered);
        $this->assertStringNotContainsString('javascript:', $rendered);
    }

    public function test_it_adds_frontend_fit_text_classes_to_headings(): void
    {
        $html = '<!-- wp:heading {"fitText":true} --><h2>Scale me</h2><!-- /wp:heading -->';

        $rendered = (string) app(BlockRenderer::class)->render($html);

        $this->assertStringContainsString('class="wp-block-heading has-fit-text"', $rendered);
        $this->assertStringContainsString('<h2', $rendered);
    }

    public function test_it_applies_wrapper_supports_to_constructed_headings(): void
    {
        $html = '<!-- wp:heading {"level":3,"content":"Title <em>here</em><script>alert(1)</script>","align":"wide","anchor":"heading-one","className":"extra-heading","textColor":"blue","fontSize":"large","style":{"typography":{"textAlign":"right"},"spacing":{"margin":{"top":"1rem"}}}} /-->';

        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('<h3', $rendered);
        $this->assertStringContainsString('class="wp-block-heading alignwide extra-heading has-blue-color has-text-color has-large-font-size has-text-align-right"', $rendered);
        $this->assertStringContainsString('id="heading-one"', $rendered);
        $this->assertStringContainsString('style="margin-top: 1rem"', $rendered);
        $this->assertStringContainsString('Title <em>here</em>', $rendered);
        $this->assertStringNotContainsString('<script>', $rendered);
    }

    public function test_it_adds_lightbox_frontend_markup_to_enabled_images(): void
    {
        $html = '<!-- wp:image {"lightbox":{"enabled":true}} --><figure class="wp-block-image"><img src="/assets/photo.jpg" alt="Photo"></figure><!-- /wp:image -->';

        $rendered = (string) app(BlockRenderer::class)->render($html);

        $this->assertStringContainsString('wp-lightbox-container', $rendered);
        $this->assertStringContainsString('data-sgb-lightbox="true"', $rendered);
        $this->assertStringContainsString('data-sgb-lightbox-trigger="true"', $rendered);
    }

    public function test_it_renders_statamic_asset_attachment_images_from_ids(): void
    {
        $this->bindFakeAsset('assets::hero.jpg');

        $image = wp_get_attachment_image('asset::assets::hero.jpg', 'large', false, [
            'class' => 'avtb__image',
            'loading' => 'lazy',
            'decoding' => 'async',
        ]);

        $this->assertStringContainsString('src="/storage/assets/hero.jpg"', $image);
        $this->assertStringContainsString('alt="Hero alt"', $image);
        $this->assertStringContainsString('class="avtb__image"', $image);
        $this->assertStringContainsString('loading="lazy"', $image);
        $this->assertStringContainsString('decoding="async"', $image);
        $this->assertStringContainsString('width="1200"', $image);
        $this->assertStringContainsString('height="800"', $image);
        $this->assertSame('/storage/assets/hero.jpg', wp_get_attachment_url('assets::hero.jpg'));
        $this->assertSame('', wp_get_attachment_image('assets::missing.jpg'));
    }

    public function test_it_constructs_core_images_from_statamic_asset_ids_when_url_is_missing(): void
    {
        $this->bindFakeAsset('assets::hero.jpg');

        $html = '<!-- wp:image {"id":"assets::hero.jpg","align":"wide","anchor":"image-one","className":"image-extra","style":{"spacing":{"margin":{"top":"1rem"}},"border":{"radius":"16px"}}} /-->';
        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('class="wp-block-image alignwide image-extra"', $rendered);
        $this->assertStringContainsString('id="image-one"', $rendered);
        $this->assertStringContainsString('margin-top: 1rem', $rendered);
        $this->assertStringContainsString('border-radius: 16px', $rendered);
        $this->assertStringContainsString('src="/storage/assets/hero.jpg"', $rendered);
        $this->assertStringContainsString('alt="Hero alt"', $rendered);
        $this->assertStringContainsString('width="1200"', $rendered);
        $this->assertStringContainsString('height="800"', $rendered);
    }

    public function test_it_constructs_core_images_from_urls_with_wrapper_supports(): void
    {
        $html = '<!-- wp:image {"url":"/storage/photo.jpg","alt":"Photo","align":"wide","anchor":"image-url-one","className":"image-url-extra","style":{"spacing":{"margin":{"bottom":"1.5rem"}}}} /-->';
        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('class="wp-block-image alignwide image-url-extra"', $rendered);
        $this->assertStringContainsString('id="image-url-one"', $rendered);
        $this->assertStringContainsString('margin-bottom: 1.5rem', $rendered);
        $this->assertStringContainsString('src="/storage/photo.jpg"', $rendered);
        $this->assertStringContainsString('alt="Photo"', $rendered);
    }

    public function test_it_renders_icon_blocks_with_wrapper_supports_and_accessible_label(): void
    {
        config([
            'statamic-gutenberg.icons' => [
                'star' => '<svg viewBox="0 0 24 24"><path d="M12 2l3 7h7l-5.5 4.5L18 21l-6-4-6 4 1.5-7.5L2 9h7z"/></svg>',
            ],
        ]);
        $this->app->forgetInstance(IconRepository::class);

        $html = '<!-- wp:icon {"icon":"star","ariaLabel":"Favorite","align":"center","className":"icon-extra","anchor":"icon-one","textColor":"primary","style":{"spacing":{"margin":{"top":"1rem"}}}} /-->';
        $rendered = (string) app(BlockRenderer::class)->render($html, $this->allCoreAllowedOptions());

        $this->assertStringContainsString('class="wp-block-icon aligncenter icon-extra has-primary-color has-text-color"', $rendered);
        $this->assertStringContainsString('id="icon-one"', $rendered);
        $this->assertStringContainsString('style="margin-top: 1rem"', $rendered);
        $this->assertStringContainsString('role="img"', $rendered);
        $this->assertStringContainsString('aria-label="Favorite"', $rendered);
        $this->assertStringNotContainsString('aria-hidden="true"', $rendered);
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

    private function bindFakeAsset(string $id): void
    {
        $asset = new class
        {
            public function url(): string
            {
                return '/storage/assets/hero.jpg';
            }

            public function isImage(): bool
            {
                return true;
            }

            public function isSvg(): bool
            {
                return false;
            }

            public function get(string $key, mixed $fallback = null): mixed
            {
                return $key === 'alt' ? 'Hero alt' : $fallback;
            }

            public function width(): int
            {
                return 1200;
            }

            public function height(): int
            {
                return 800;
            }
        };

        $this->app->instance(AssetRepositoryContract::class, new class($id, $asset) implements AssetRepositoryContract
        {
            public function __construct(private string $id, private mixed $asset)
            {
                //
            }

            public function all()
            {
                return collect([$this->asset]);
            }

            public function whereContainer(string $container)
            {
                return collect([$this->asset]);
            }

            public function whereFolder(string $folder, string $container)
            {
                return collect([$this->asset]);
            }

            public function find(string $asset)
            {
                return $asset === $this->id ? $this->asset : null;
            }

            public function findByUrl(string $url)
            {
                return $url === '/storage/assets/hero.jpg' ? $this->asset : null;
            }

            public function findById(string $id)
            {
                return $this->find($id);
            }

            public function findByPath(string $path)
            {
                return null;
            }

            public function findOrFail(string $asset)
            {
                return $this->find($asset);
            }

            public function make()
            {
                return null;
            }

            public function query()
            {
                return null;
            }

            public function save($asset)
            {
                //
            }
        });

        AssetFacade::clearResolvedInstance(AssetRepositoryContract::class);
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
