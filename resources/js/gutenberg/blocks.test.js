import fs from 'node:fs';
import { describe, expect, it } from 'vitest';
import {
    addTextAlignSaveProps,
    CONTAINER_SUPPORT_BLOCKS,
    MEDIA_SUPPORT_BLOCKS,
    STATAMIC_MEDIA_IDENTITY_BLOCKS,
    STATAMIC_SUPPORT_BLOCKS,
    TEXT_ALIGNMENTS,
    TEXT_FORMATTING_BLOCKS,
    TEXT_SUPPORT_BLOCKS,
    UTILITY_SUPPORT_BLOCKS,
    WIDE_FULL_ALIGNMENTS,
    textAlignClassName,
    withStatamicMediaIdentitySupport,
    withStatamicBlockSupport,
    withTextFormattingSupport,
    withWideFullAlignSupport,
} from './blockSupport';
import {
    SUPPORTED_EMBED_PROVIDER_SLUGS,
    unregisterUnsupportedEmbedVariations,
} from './embedProviders';

describe('registerGutenbergBlocks', () => {
    it('enables experimental and form core blocks before registering block-library blocks', () => {
        const source = fs.readFileSync('resources/js/gutenberg/blocks.jsx', 'utf8');
        const experimentsIndex = source.indexOf('window.__experimentalEnableBlockExperiments = true');
        const formsIndex = source.indexOf('window.__experimentalEnableFormBlocks = true');
        const registerIndex = source.indexOf('registerCoreBlocks();');

        expect(experimentsIndex).toBeGreaterThan(-1);
        expect(formsIndex).toBeGreaterThan(-1);
        expect(registerIndex).toBeGreaterThan(-1);
        expect(experimentsIndex).toBeLessThan(registerIndex);
        expect(formsIndex).toBeLessThan(registerIndex);
    });

    it('registers Statamic block support before core blocks are registered', () => {
        const source = fs.readFileSync('resources/js/gutenberg/blocks.jsx', 'utf8');
        const filterCallIndex = source.indexOf('registerStatamicBlockFilters();');
        const registerIndex = source.indexOf('registerCoreBlocks();');

        expect(filterCallIndex).toBeGreaterThan(-1);
        expect(source).toContain("from './blockSupport'");
        expect(source).toContain("'blocks.registerBlockType'");
        expect(source).toContain('withStatamicBlockSupport');
        expect(source).toContain("'editor.BlockEdit'");
        expect(source).toContain('withStatamicTextAlignmentControls');
        expect(source).toContain("'blocks.getSaveContent.extraProps'");
        expect(source).toContain('addTextAlignSaveProps');
        expect(filterCallIndex).toBeLessThan(registerIndex);
    });

    it('keeps only supported core embed variations in the inserter', () => {
        const source = fs.readFileSync('resources/js/gutenberg/blocks.jsx', 'utf8');
        const removed = [];

        unregisterUnsupportedEmbedVariations({
            getBlockVariations: () => [
                { name: 'youtube' },
                { name: 'vimeo' },
                { name: 'spotify' },
                { name: 'soundcloud' },
                { name: 'twitter' },
                { name: 'reddit' },
            ],
            unregisterBlockVariation: (blockName, variationName) => removed.push([blockName, variationName]),
        });

        expect(SUPPORTED_EMBED_PROVIDER_SLUGS).toEqual(['youtube', 'vimeo', 'spotify', 'soundcloud']);
        expect(source).toContain('unregisterUnsupportedEmbedVariations');
        expect(removed).toEqual([
            ['core/embed', 'twitter'],
            ['core/embed', 'reddit'],
        ]);
    });

    it('merges wide and full align support into block settings', () => {
        expect(WIDE_FULL_ALIGNMENTS).toEqual(['wide', 'full']);
        expect(withWideFullAlignSupport({ supports: {} }).supports.align).toEqual(['wide', 'full']);
        expect(withWideFullAlignSupport({ supports: { align: 'center' } }).supports.align).toEqual(['center', 'wide', 'full']);
        expect(withWideFullAlignSupport({ supports: { align: ['left', 'wide'] } }).supports.align).toEqual(['left', 'wide', 'full']);

        const settings = withWideFullAlignSupport({ supports: { align: true } });
        expect(settings.supports.align).toBe(true);
        expect(settings.attributes.align).toEqual({
            type: 'string',
            enum: ['left', 'center', 'right', 'wide', 'full', ''],
        });

        const disabled = withWideFullAlignSupport({ supports: { align: false }, attributes: {} });
        expect(disabled.supports.align).toBe(false);
        expect(disabled.attributes.align).toBeUndefined();
    });

    it('adds paragraph and heading text alignment and color support without overriding existing support values', () => {
        expect(TEXT_FORMATTING_BLOCKS).toEqual(['core/paragraph', 'core/heading']);
        expect(TEXT_ALIGNMENTS).toEqual(['left', 'center', 'right', 'justify']);

        const settings = withTextFormattingSupport({
            attributes: {},
            supports: {
                typography: { fontSize: true, textAlign: true },
                color: { gradients: true },
            },
        }, 'core/paragraph');

        expect(settings.supports.typography).toMatchObject({
            fontSize: true,
            textAlign: true,
            textColumns: true,
            textIndent: true,
        });
        expect(settings.supports.color).toMatchObject({
            gradients: true,
            text: true,
        });
        expect(settings.attributes.style).toEqual({ type: 'object' });
        expect(withTextFormattingSupport({ supports: {} }, 'core/image').supports).toEqual({});
    });

    it('registers Statamic asset identity on core media blocks', () => {
        expect(STATAMIC_MEDIA_IDENTITY_BLOCKS).toContain('core/image');
        expect(STATAMIC_MEDIA_IDENTITY_BLOCKS).toContain('core/media-text');
        expect(withStatamicMediaIdentitySupport({ attributes: {} }, 'core/image').attributes.statamicId)
            .toEqual({ type: 'string' });
        expect(withStatamicMediaIdentitySupport({ attributes: {} }, 'core/paragraph').attributes)
            .toEqual({});
    });

    it('combines align, text formatting and text align save props', () => {
        const settings = withStatamicBlockSupport({ supports: { align: ['wide'] }, attributes: {} }, 'core/heading');

        expect(settings.supports.align).toEqual(['wide', 'full']);
        expect(settings.supports.typography.textAlign).toBe(true);
        expect(settings.supports.typography.textColumns).toBe(true);
        expect(settings.supports.typography.textIndent).toBe(true);
        expect(settings.supports.color.text).toBe(true);
        expect(textAlignClassName({ style: { typography: { textAlign: 'justify' } } })).toBe('has-text-align-justify');
        expect(textAlignClassName({ style: { typography: { textAlign: 'invalid' } } })).toBe('');

        expect(addTextAlignSaveProps(
            { className: 'wp-block-heading' },
            { name: 'core/heading' },
            { style: { typography: { textAlign: 'right' } } },
        )).toEqual({
            className: 'wp-block-heading has-text-align-right',
        });

        expect(addTextAlignSaveProps(
            { className: 'wp-block-image' },
            { name: 'core/image' },
            { style: { typography: { textAlign: 'justify' } } },
        )).toEqual({
            className: 'wp-block-image',
        });

        expect(withStatamicBlockSupport({ supports: {}, attributes: {} }, 'core/image').attributes.statamicId)
            .toEqual({ type: 'string' });
    });

    it('adds a Gutenberg support matrix for allowed core block groups', () => {
        expect(TEXT_SUPPORT_BLOCKS).toContain('core/list');
        expect(CONTAINER_SUPPORT_BLOCKS).toContain('core/group');
        expect(MEDIA_SUPPORT_BLOCKS).toContain('core/image');
        expect(UTILITY_SUPPORT_BLOCKS).toContain('core/table');
        expect(STATAMIC_SUPPORT_BLOCKS).toContain('core/spacer');

        const group = withStatamicBlockSupport({ supports: {}, attributes: {} }, 'core/group');

        expect(group.supports).toMatchObject({
            align: ['wide', 'full'],
            anchor: true,
            className: true,
            shadow: true,
            layout: true,
            allowedBlocks: true,
            color: {
                text: true,
                background: true,
                gradients: true,
                link: true,
            },
            typography: {
                fontSize: true,
                lineHeight: true,
                __experimentalFontFamily: true,
                __experimentalTextDecoration: true,
            },
            spacing: {
                margin: true,
                padding: true,
                blockGap: true,
            },
            __experimentalBorder: {
                color: true,
                radius: true,
                style: true,
                width: true,
            },
            dimensions: {
                aspectRatio: true,
                minHeight: true,
                minWidth: true,
                width: true,
            },
            background: {
                backgroundImage: true,
                backgroundSize: true,
            },
        });
        expect(group.attributes).toMatchObject({
            style: { type: 'object' },
            align: { type: 'string' },
            anchor: { type: 'string' },
            className: { type: 'string' },
            textColor: { type: 'string' },
            backgroundColor: { type: 'string' },
            gradient: { type: 'string' },
            fontSize: { type: 'string' },
            fontFamily: { type: 'string' },
            borderColor: { type: 'string' },
        });

        const button = withStatamicBlockSupport({ supports: { align: false }, attributes: {} }, 'core/button');

        expect(button.supports.align).toBe(false);
        expect(button.attributes.align).toBeUndefined();
    });

    it('keeps custom block supports authoritative while adding support attributes', () => {
        const custom = withStatamicBlockSupport({
            attributes: {},
            supports: {
                align: false,
                color: {
                    text: true,
                    background: false,
                },
                typography: {
                    fontSize: true,
                    fontFamily: false,
                },
            },
        }, 'amazing/card');

        expect(custom.supports.align).toBe(false);
        expect(custom.supports.color).toEqual({
            text: true,
            background: false,
        });
        expect(custom.supports.typography).toEqual({
            fontSize: true,
            fontFamily: false,
        });
        expect(custom.attributes.align).toBeUndefined();
        expect(custom.attributes.style).toEqual({ type: 'object' });
        expect(custom.attributes.textColor).toEqual({ type: 'string' });
        expect(custom.attributes.fontSize).toEqual({ type: 'string' });

        const customWithoutSupports = withStatamicBlockSupport({ supports: {}, attributes: {} }, 'amazing/plain');

        expect(customWithoutSupports.supports).toEqual({});
        expect(customWithoutSupports.attributes).toEqual({});
    });

    it('adds editor wrapper styles for group flex and grid layouts', () => {
        const source = fs.readFileSync('resources/js/gutenberg/blocks.jsx', 'utf8');

        expect(source).toContain("'editor.BlockListBlock'");
        expect(source).toContain('withStatamicLayoutWrapperStyles');
        expect(source).toContain('layout.type === \'grid\'');
        expect(source).toContain('style.gridTemplateColumns');
        expect(source).toContain('const minimumColumnWidth = safeLayoutSize(layout.minimumColumnWidth)');
        expect(source).toContain('function safeLayoutSize(value)');
        expect(source).toContain('(?:url|expression|javascript|;|{|}|<|>)');
        expect(source).toContain('layout.type === \'flex\'');
        expect(source).toContain('style.textAlign = textAlign');
        expect(source).toContain('style.justifyContent = cssJustification(layout.justifyContent || layout.contentJustification || \'left\')');
        expect(source).toContain("case 'right':");
        expect(source).toContain("return 'flex-end'");
    });

    it('feeds Statamic patterns into native Gutenberg editor settings', () => {
        const source = fs.readFileSync('resources/js/gutenberg/GutenbergEditor.jsx', 'utf8');
        const fieldtypeSource = fs.readFileSync('resources/js/components/fieldtypes/StatamicGutenberg.vue', 'utf8');
        const patternSettingsSource = fs.readFileSync('resources/js/gutenberg/patternSettings.js', 'utf8');

        expect(source).toContain('window.StatamicGutenbergPatterns = patternSettings');
        expect(source).toContain('window.StatamicGutenbergPatterns');
        expect(source).toContain("import { applyPatternSettings, filterPatternPayload } from './patternSettings'");
        expect(source).toContain('filterPatternPayload(rawPatternSettings, allowedBlockTypes)');
        expect(fieldtypeSource).toContain('meta.patternsUrl');
        expect(fieldtypeSource).toContain('window.StatamicGutenbergPatterns = meta.patterns');
        expect(patternSettingsSource).toContain('__experimentalReusableBlocks');
        expect(patternSettingsSource).toContain('__experimentalUserPatternCategories');
        expect(patternSettingsSource).toContain('__experimentalBlockPatterns');
        expect(patternSettingsSource).toContain('__experimentalBlockPatternCategories');
        expect(patternSettingsSource).toContain('reusableBlocksForInserter');
    });

    it('feeds theme.json duotone presets and filters into the editor', () => {
        const source = fs.readFileSync('resources/js/gutenberg/GutenbergEditor.jsx', 'utf8');

        expect(source).toContain('const duotones = presetList(color.duotone);');
        expect(source).toContain("assignThemePreset(next, ['color', 'duotone'], duotones);");
        expect(source).toContain('duotone: {');
        expect(source).toContain('const themeJsonSvgs = typeof meta.themeJson?.svgs === \'string\' ? meta.themeJson.svgs : \'\';');
        expect(source).toContain('className="sgb-duotone-filters"');
        expect(source).toContain('dangerouslySetInnerHTML={{ __html: themeJsonSvgs }}');
    });

    it('feeds theme.json dimension presets into the editor', () => {
        const source = fs.readFileSync('resources/js/gutenberg/GutenbergEditor.jsx', 'utf8');

        expect(source).toContain('aspectRatio: true');
        expect(source).toContain('const aspectRatios = presetList(dimensions.aspectRatios);');
        expect(source).toContain("assignThemePreset(next, ['dimensions', 'aspectRatios'], aspectRatios);");
        expect(source).toContain('minWidth: true');
        expect(source).toContain('width: true');
    });

    it('feeds theme.json shadow presets into the editor', () => {
        const source = fs.readFileSync('resources/js/gutenberg/GutenbergEditor.jsx', 'utf8');

        expect(source).toContain('shadow: {');
        expect(source).toContain('const shadow = isPlainObject(themeSettings.shadow) ? themeSettings.shadow : {};');
        expect(source).toContain('const shadowPresets = presetList(shadow.presets);');
        expect(source).toContain("assignThemePreset(next, ['shadow', 'presets'], shadowPresets);");
    });

    it('registers Statamic block styles with the native Gutenberg style registry', () => {
        const source = fs.readFileSync('resources/js/gutenberg/GutenbergEditor.jsx', 'utf8');
        const fieldtypeSource = fs.readFileSync('resources/js/components/fieldtypes/StatamicGutenberg.vue', 'utf8');

        expect(source).toContain("import { registerStatamicBlockStyles } from './blockStyles'");
        expect(source).toContain('const rawBlockStyles = Array.isArray(meta?.blockStyles)');
        expect(source).toContain('registerStatamicBlockStyles(rawBlockStyles)');
        expect(source).toContain('window.StatamicGutenbergBlockStyles = blockStyles');
        expect(fieldtypeSource).toContain('window.StatamicGutenbergBlockStyles = meta.blockStyles');
    });

    it('inserts selected Statamic gallery assets as nested image blocks', () => {
        const source = fs.readFileSync('resources/js/gutenberg/GutenbergEditor.jsx', 'utf8');

        expect(source).toContain('createImageBlock');
        expect(source).toContain("selectedBlock?.name === 'core/gallery'");
        expect(source).toContain('multiple: Boolean(options.multiple || isSelectedGallery)');
        expect(source).toContain('selected.map((selectedAsset) => createImageBlock(selectedAsset))');
        expect(source).toContain('uploaded.map((asset) => createImageBlock(asset))');
        expect(source).toContain('assetPicker?.multiple');
    });

    it('exposes a Statamic-backed media library flow to Gutenberg', () => {
        const source = fs.readFileSync('resources/js/gutenberg/GutenbergEditor.jsx', 'utf8');
        const apiFetchSource = fs.readFileSync('resources/js/gutenberg/apiFetchFallbacks.js', 'utf8');
        const fieldtypeSource = fs.readFileSync('src/Fieldtypes/Gutenberg.php', 'utf8');
        const css = fs.readFileSync('resources/css/addon.css', 'utf8');

        expect(source).toContain('window.StatamicGutenbergAssetsUrl = meta.assetsUrl');
        expect(source).toContain('window.StatamicGutenbergUploadUrl = meta.uploadUrl');
        expect(source).toContain('window.StatamicGutenbergMediaUrl = meta.mediaUrl');
        expect(source).toContain('window.StatamicGutenbergAssetsContainer = meta.assetsContainer');
        expect(source).toContain('TextareaControl');
        expect(source).toContain('collectAssetTreeKeys(assetContainers)');
        expect(source).toContain('sgb-assets__tree-icon--container');
        expect(source).toContain('sgb-assets__tree-icon--folder');
        expect(source).toContain("'⊕'");
        expect(source).toContain("'⊖'");
        expect(source).toContain('setAssetContainer');
        expect(source).toContain('setAssetFolderTree(Array.isArray(json.folder_tree) ? json.folder_tree : [])');
        expect(source).toContain('toggleFolderExpanded');
        expect(source).toContain('⊕ Expand all');
        expect(source).toContain('⊖ Collapse all');
        expect(source).toContain('updateFocusedAssetMetadata');
        expect(source).toContain('Save details');
        expect(source).toContain('onDoubleClick={() => insertSelectedAssets([asset])}');
        expect(apiFetchSource).toContain('fetchStatamicMediaList');
        expect(apiFetchSource).toContain("url.searchParams.set('container', wpUrl.searchParams.get('statamic_container') || '*')");
        expect(apiFetchSource).toContain('updateStatamicMedia');
        expect(apiFetchSource).toContain('uploadStatamicMedia');
        expect(fieldtypeSource).toContain("'mediaUrl' => cp_route('amazingbv.statamic-gutenberg.assets.show')");
        expect(css).toContain('.sgb-assets__filters');
        expect(css).toContain('.sgb-assets__tree-icon--container');
        expect(css).toContain('.sgb-assets__tree-icon--folder');
        expect(css).toContain('.sgb-assets__tree-shell');
        expect(css).toContain('.sgb-assets__tree-actions');
        expect(css).toContain('.sgb-assets__details');
    });

    it('adds native-style editor controls for history, list view and code mode', () => {
        const source = fs.readFileSync('resources/js/gutenberg/GutenbergEditor.jsx', 'utf8');
        const windowSource = fs.readFileSync('resources/js/gutenberg/GutenbergWindow.jsx', 'utf8');
        const css = fs.readFileSync('resources/css/addon.css', 'utf8');

        expect(source).toContain('HISTORY_LIMIT');
        expect(source).toContain('undoEdit');
        expect(source).toContain('redoEdit');
        expect(source).toContain('onKeyDownCapture={handleEditorKeyDown}');
        expect(source).toContain("key === 'z'");
        expect(source).toContain("key === 'y'");
        expect(source).toContain('sgb-editor__workspace--list-open');
        expect(source).toContain('sgb-list-view');
        expect(source).toContain('switchEditorMode');
        expect(source).toContain('Code editor');
        expect(source).toContain('Visual editor');
        expect(source).toContain('sgb-code-editor');
        expect(source).toContain('highlightCode');
        expect(source).toContain('highlightJson');
        expect(source).toContain('sgb-code-highlight');
        expect(source).toContain('syncCodeHighlightScroll');
        expect(source).toContain('validateSerialized');
        expect(source).toContain('codeError');
        expect(source).toContain('onValidityChange');
        expect(source).toContain('wrap="off"');
        expect(css).toContain('.sgb-token--tag-name');
        expect(css).toContain('.sgb-token--json-key');
        expect(css).toContain('.sgb-token--block-name');
        expect(css).toContain('.sgb-code-editor__error');
        expect(windowSource).toContain('value !== lastAppliedValue');
        expect(windowSource).toContain('editorValid');
        expect(windowSource).toContain('Fix code editor syntax before applying');
        expect(windowSource).toContain('try {');
        expect(windowSource).toContain('Large embedded entries do not need storage handoff');
        expect(windowSource).not.toContain('Close the block editor without applying changes?');
    });
});
