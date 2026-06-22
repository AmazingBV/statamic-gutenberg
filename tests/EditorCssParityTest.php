<?php

namespace Amazingbv\StatamicGutenberg\Tests;

class EditorCssParityTest extends TestCase
{
    public function test_fullscreen_editor_reuses_frontend_block_layout_styles(): void
    {
        $css = file_get_contents(__DIR__.'/../resources/css/addon.css');

        $this->assertStringContainsString('grid-template-columns:', $css);
        $this->assertStringContainsString('[content-start] minmax(0, var(--wp--style--global--content-size))', $css);
        $this->assertStringContainsString('font-size: clamp(2rem, 4vw, 3rem)', $css);
        $this->assertStringContainsString('font-weight: 700', $css);
        $this->assertStringContainsString('.sgb-editor .sgb-page-frame .wp-block-heading.block-editor-rich-text__editable', $css);
        $this->assertStringContainsString('margin-block-start: var(--wp--style--block-gap)', $css);
        $this->assertStringContainsString('.sgb-editor--fullscreen .sgb-page-frame .wp-block-image', $css);
        $this->assertStringContainsString('list-style-type: disc', $css);
        $this->assertStringContainsString('list-style-type: decimal', $css);
        $this->assertStringContainsString('.sgb-editor .sgb-page-frame .wp-block-list li.block-editor-block-list__block', $css);
        $this->assertStringContainsString('list-style: inherit', $css);
        $this->assertStringContainsString('.sgb-editor .sgb-page-frame :is(.is-layout-grid)', $css);
        $this->assertStringContainsString('.sgb-editor .sgb-page-frame .sgb-core-fallback', $css);
        $this->assertStringContainsString('.sgb-editor--fullscreen .sgb-page-frame .wp-block-cover', $css);
        $this->assertStringContainsString('.sgb-editor--fullscreen .sgb-page-frame .wp-block-media-text', $css);
        $this->assertStringContainsString('display: grid', $css);
        $this->assertStringContainsString('display: flex', $css);
    }

    public function test_fullscreen_editor_does_not_render_the_entry_title_in_the_canvas(): void
    {
        $css = file_get_contents(__DIR__.'/../resources/css/addon.css');
        $editor = file_get_contents(__DIR__.'/../resources/js/gutenberg/GutenbergEditor.jsx');
        $window = file_get_contents(__DIR__.'/../resources/js/gutenberg/GutenbergWindow.jsx');

        $this->assertStringContainsString("@wordpress/block-library/build-style/editor.css", $editor);
        $this->assertStringContainsString("@wordpress/block-library/build-style/style.css", $editor);
        $this->assertStringContainsString("@wordpress/block-library/build-style/theme.css", $editor);
        $this->assertStringContainsString('alignWide: true', $editor);
        $this->assertStringContainsString('supportsLayout: true', $editor);
        $this->assertStringContainsString("contentSize: CONTENT_SIZE", $editor);
        $this->assertStringContainsString("wideSize: WIDE_SIZE", $editor);
        $this->assertStringContainsString("blockGap: true", $editor);
        $this->assertStringNotContainsString('sgb-page-title', $css.$editor);
        $this->assertStringContainsString('<h1>Gutenberg Editor</h1>', $window);
        $this->assertStringNotContainsString('title={payload.title || title}', $window);
    }
}
