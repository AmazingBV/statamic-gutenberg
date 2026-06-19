<?php

namespace Amazingbv\StatamicGutenberg\Tests;

use Amazingbv\StatamicGutenberg\Blocks\BlockRenderer;
use Amazingbv\StatamicGutenberg\GutenbergManager;

class BlockRendererTest extends TestCase
{
    public function test_it_renders_allowed_core_blocks_and_sanitizes_html(): void
    {
        $html = '<!-- wp:paragraph --><p onclick="alert(1)">Hello<script>alert(1)</script></p><!-- /wp:paragraph -->';

        $rendered = (string) app(BlockRenderer::class)->render($html);

        $this->assertStringContainsString('<p>Hello</p>', $rendered);
        $this->assertStringNotContainsString('onclick', $rendered);
        $this->assertStringNotContainsString('script', $rendered);
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
