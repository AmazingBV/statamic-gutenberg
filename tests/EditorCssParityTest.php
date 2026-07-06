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
        $this->assertStringContainsString('.sgb-editor .sgb-page-frame :where(.has-text-align-justify)', $css);
        $this->assertStringContainsString('.sgb-editor .sgb-page-frame :where(.has-blue-color)', $css);
        $this->assertStringContainsString('.sgb-editor .sgb-page-frame :where(.has-blue-background-color)', $css);
        $this->assertStringContainsString('.sgb-editor .sgb-page-frame :where(.has-blue-border-color)', $css);
        $this->assertStringContainsString('.sgb-editor .sgb-page-frame :where(.has-link-color) a', $css);
        $this->assertStringContainsString('.sgb-editor .sgb-page-frame :where(.has-blue-to-green-gradient-background)', $css);
        $this->assertStringContainsString('--wp--preset--gradient--blue-to-green', $css);
        $this->assertStringContainsString('.sgb-editor .sgb-page-frame :is(.is-layout-grid)', $css);
        $this->assertStringContainsString('grid-template-columns: repeat(auto-fill, minmax(min(12rem, 100%), 1fr))', $css);
        $this->assertStringContainsString('.sgb-editor .sgb-page-frame :is(.is-layout-flex, .is-layout-grid) > * + *', $css);
        $this->assertStringContainsString('.sgb-editor--fullscreen .sgb-page-frame :is(.is-layout-constrained) > :where(:not(.alignleft):not(.alignright):not(.alignfull))', $css);
        $this->assertStringContainsString('max-width: var(--wp--style--global--content-size)', $css);
        $this->assertStringContainsString('.sgb-editor--fullscreen .sgb-page-frame :is(.is-layout-constrained) > .alignwide', $css);
        $this->assertStringContainsString('max-width: var(--wp--style--global--wide-size)', $css);
        $this->assertStringContainsString('.sgb-editor .sgb-page-frame .sgb-core-fallback', $css);
        $this->assertStringContainsString('.sgb-editor--fullscreen .sgb-page-frame .wp-block-cover', $css);
        $this->assertStringContainsString('.sgb-editor--fullscreen .sgb-page-frame .wp-block-media-text', $css);
        $this->assertStringContainsString('.sgb-editor--fullscreen .sgb-page-frame .wp-block-media-text.is-stacked-on-mobile', $css);
        $this->assertStringContainsString('.sgb-editor--fullscreen .sgb-page-frame .wp-block-media-text.is-image-fill-element > .wp-block-media-text__media img', $css);
        $this->assertStringContainsString('object-fit: cover', $css);
        $this->assertStringContainsString('.sgb-editor .sgb-page-frame .wp-block-embed iframe', $css);
        $this->assertStringContainsString('.sgb-editor .sgb-page-frame .wp-block-embed.is-type-video iframe', $css);
        $this->assertStringContainsString('.sgb-editor .sgb-page-frame .wp-block-embed-spotify iframe', $css);
        $this->assertStringContainsString('.sgb-editor .sgb-page-frame .wp-block-embed-soundcloud iframe', $css);
        $this->assertStringContainsString('.sgb-editor--fullscreen .sgb-page-frame .wp-block-columns', $css);
        $this->assertStringNotContainsString('gap: 2em', $css);
        $this->assertStringContainsString('.sgb-editor .sgb-page-frame .wp-block-columns:is(.wp-block-columns)', $css);
        $this->assertStringContainsString('.sgb-editor .sgb-page-frame .wp-block-columns.are-vertically-aligned-center', $css);
        $this->assertStringContainsString('.sgb-editor .sgb-page-frame .wp-block-column.is-vertically-aligned-bottom', $css);
        $this->assertStringContainsString('display: grid', $css);
        $this->assertStringContainsString('display: flex', $css);
    }

    public function test_frontend_css_includes_default_background_border_and_gradient_utilities(): void
    {
        $css = file_get_contents(__DIR__.'/../resources/css/frontend.css');

        $this->assertStringContainsString('.sgb-content :where(.has-blue-background-color)', $css);
        $this->assertStringContainsString('background-color: var(--wp--preset--color--blue) !important', $css);
        $this->assertStringContainsString('.sgb-content :where(.has-blue-border-color)', $css);
        $this->assertStringContainsString('border-color: var(--wp--preset--color--blue) !important', $css);
        $this->assertStringContainsString('.sgb-content :where(.has-link-color) a', $css);
        $this->assertStringContainsString('color: var(--wp--style--color--link)', $css);
        $this->assertStringContainsString('.sgb-content :where(.has-blue-to-green-gradient-background)', $css);
        $this->assertStringContainsString('background: var(--wp--preset--gradient--blue-to-green) !important', $css);
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
        $this->assertStringContainsString('__unstableIsBlockBasedTheme: false', $editor);
        $this->assertStringContainsString('const ROOT_BLOCK_LAYOUT = {', $editor);
        $this->assertStringContainsString('applyThemeJsonSettings', $editor);
        $this->assertStringContainsString('meta.themeJson', $editor);
        $this->assertStringContainsString('data-statamic-gutenberg-theme-json', $editor);
        $this->assertStringContainsString('<BlockList layout={rootBlockLayout} />', $editor);
        $this->assertStringContainsString("import '@wordpress/format-library';", $editor);
        $this->assertStringContainsString('hasFixedToolbar: false', $editor);
        $this->assertStringContainsString('inserterMediaCategories: []', $editor);
        $this->assertStringContainsString('__unstableContentRef={editorContentRef}', $editor);
        $this->assertStringContainsString("contentSize: CONTENT_SIZE", $editor);
        $this->assertStringContainsString("wideSize: WIDE_SIZE", $editor);
        $this->assertStringContainsString("blockGap: true", $editor);
        $this->assertStringContainsString('text: true', $editor);
        $this->assertStringContainsString('link: true', $editor);
        $this->assertStringContainsString('textColumns: true', $editor);
        $this->assertStringContainsString('textIndent: true', $editor);
        $this->assertStringNotContainsString('image as imageIcon', $editor);
        $this->assertStringNotContainsString('label="Open Statamic assets"', $editor);
        $this->assertStringContainsString('.block-editor-tabbed-sidebar__tab:nth-child(3)', $css);
        $this->assertStringNotContainsString('sgb-page-title', $css.$editor);
        $this->assertStringContainsString('<h1>Block Editor</h1>', $window);
        $this->assertStringNotContainsString('title={payload.title || title}', $window);
    }

    public function test_editor_keeps_default_full_width_blocks_inside_the_content_grid(): void
    {
        $css = file_get_contents(__DIR__.'/../resources/css/addon.css');

        $this->assertStringContainsString(
            '.sgb-editor--fullscreen .sgb-canvas > .block-editor-block-list__layout > :is(.wp-block-columns, .wp-block-group) {',
            $css
        );
        $this->assertStringContainsString(
            '.sgb-editor--fullscreen .sgb-editor__stage',
            $css
        );
        $this->assertStringContainsString('flex-direction: column', $css);
        $this->assertStringContainsString('.sgb-editor--fullscreen .sgb-toolbar', $css);
        $this->assertStringContainsString('position: relative', $css);
        $this->assertStringContainsString('flex: 0 0 auto', $css);
        $this->assertStringContainsString('flex: 1 1 auto', $css);
        $this->assertStringContainsString('padding: 0', $css);
        $this->assertStringContainsString('box-sizing: border-box', $css);
        $this->assertStringContainsString('max-width: none', $css);
        $this->assertStringContainsString('border: 0', $css);
        $this->assertStringContainsString('box-shadow: none', $css);
        $this->assertStringContainsString(
            '.sgb-editor--fullscreen .sgb-canvas > .block-editor-block-list__layout > :is(.wp-block-columns.alignwide, .wp-block-group.alignwide)',
            $css
        );
        $this->assertStringContainsString(
            '.sgb-editor--fullscreen .sgb-canvas > .block-editor-block-list__layout > .wp-block.alignfull',
            $css
        );
        $this->assertStringContainsString(
            '.sgb-editor .sgb-page-frame :where(.wp-block-list, .wp-block-details, .wp-block-math, .wp-block-accordion, .wp-block-columns, .wp-block-group)',
            $css
        );
        $this->assertStringContainsString(
            '.sgb-editor .sgb-page-frame :where(.wp-block-list, .wp-block-details, .wp-block-math, .wp-block-accordion) {',
            $css
        );
        $this->assertStringContainsString('width: 100%', $css);
        $this->assertStringNotContainsString(
            ".sgb-editor--fullscreen .sgb-canvas > .block-editor-block-list__layout > :is(.wp-block-columns, .wp-block-group) {\n    grid-column: full;",
            $css
        );
    }

    public function test_file_media_uploads_fall_back_to_file_assets_when_wordpress_passes_no_allowed_types(): void
    {
        $editor = file_get_contents(__DIR__.'/../resources/js/gutenberg/GutenbergEditor.jsx');

        $this->assertStringContainsString("values.includes('*')", $editor);
        $this->assertStringContainsString("type.startsWith('application/')", $editor);
        $this->assertStringContainsString('assetFilterFromAllowedTypes(allowedTypes)', $editor);
        $this->assertStringContainsString("url.searchParams.append('mime_types[]', mimeType)", $editor);
        $this->assertStringContainsString("url.searchParams.append('extensions[]', extension)", $editor);
        $this->assertStringContainsString("formData.append('mime_types[]', mimeType)", $editor);
        $this->assertStringContainsString("formData.append('extensions[]', extension)", $editor);
        $this->assertStringContainsString('accept={acceptForAssetPicker(assetPicker)}', $editor);
        $this->assertStringContainsString("requestedViaMediaUpload ? 'file' : 'image'", $editor);
        $this->assertStringContainsString("typeFromAllowedTypes(allowedTypes) || 'file'", $editor);
        $this->assertStringContainsString("callback.onSelect(uploaded.map((asset) => createMediaPayload(asset)))", $editor);
        $this->assertStringContainsString('function StatamicMediaUpload({ render, value, ...options })', $editor);
    }

    public function test_asset_picker_supports_selecting_multiple_existing_assets(): void
    {
        $css = file_get_contents(__DIR__.'/../resources/css/addon.css');
        $editor = file_get_contents(__DIR__.'/../resources/js/gutenberg/GutenbergEditor.jsx');

        $this->assertStringContainsString("'core/gallery': 'image'", $editor);
        $this->assertStringContainsString('const [selectedAssets, setSelectedAssets] = useState([])', $editor);
        $this->assertStringContainsString('const toggleSelectedAsset = useCallback((asset) => {', $editor);
        $this->assertStringContainsString('const insertSelectedAssets = useCallback((assetsToInsert = selectedAssets) => {', $editor);
        $this->assertStringContainsString('callback.onSelect(selected.map((asset) => createMediaPayload(asset)))', $editor);
        $this->assertStringContainsString('callback?.multiple', $editor);
        $this->assertStringContainsString('Insert selected (${selectedAssets.length})', $editor);
        $this->assertStringContainsString('aria-pressed={isMultipleAssetPicker ? isSelected : undefined}', $editor);
        $this->assertStringContainsString("className={`sgb-asset\${isSelected ? ' is-selected' : ''}`}", $editor);
        $this->assertStringContainsString('onDoubleClick={() => insertSelectedAssets([asset])}', $editor);
        $this->assertStringContainsString('.sgb-asset.is-selected', $css);
        $this->assertStringContainsString('.sgb-assets-modal', $css);
        $this->assertStringContainsString('.sgb-assets__details', $css);
        $this->assertStringContainsString('z-index: 2147482003', $css);
    }
}
