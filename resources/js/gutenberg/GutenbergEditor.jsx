import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import apiFetch from '@wordpress/api-fetch';
import {
    BlockEditorKeyboardShortcuts,
    BlockEditorProvider,
    BlockInspector,
    BlockList,
    BlockTools,
    Inserter,
    ObserveTyping,
    WritingFlow,
    __experimentalListView as ListView,
    store as blockEditorStore,
} from '@wordpress/block-editor';
import { Button, DropdownMenu, MenuGroup, MenuItem, Popover, SlotFillProvider, Spinner, TextControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { addFilter } from '@wordpress/hooks';
import {
    code as codeIcon,
    listView as listViewIcon,
    moreVertical,
    plus,
    redo as redoIcon,
    undo as undoIcon,
    update as refreshIcon,
    upload as uploadIcon,
} from '@wordpress/icons';
import { loadCustomBlockAssets, prepareCustomBlockRegistry } from './customBlocks';
import { installStatamicApiFetchFallbacks } from './apiFetchFallbacks';
import { registerGutenbergBlocks } from './blocks.jsx';
import { applyPatternSettings, filterPatternPayload } from './patternSettings';
import {
    attributesForAssetBlock,
    createAssetBlock,
    createImageBlock,
    createMediaPayload,
    normalizeAllowedBlocks,
    parseSerialized,
    serializeBlocks,
    validateSerialized,
} from './serialization';

import '@wordpress/components/build-style/style.css';
import '@wordpress/block-editor/build-style/style.css';
import '@wordpress/block-editor/build-style/content.css';
import '@wordpress/block-library/build-style/editor.css';
import '@wordpress/block-library/build-style/style.css';
import '@wordpress/block-library/build-style/theme.css';
import '@wordpress/format-library';

installStatamicApiFetchFallbacks(apiFetch);
registerGutenbergBlocks();

const CONTENT_SIZE = '760px';
const WIDE_SIZE = '1120px';
const ROOT_BLOCK_LAYOUT = {
    type: 'default',
    contentSize: CONTENT_SIZE,
    wideSize: WIDE_SIZE,
};
const HISTORY_LIMIT = 120;

const COLORS = [
    { name: 'Black', slug: 'black', color: '#111827' },
    { name: 'White', slug: 'white', color: '#ffffff' },
    { name: 'Slate', slug: 'slate', color: '#475467' },
    { name: 'Blue', slug: 'blue', color: '#315bc7' },
    { name: 'Green', slug: 'green', color: '#047857' },
    { name: 'Red', slug: 'red', color: '#b42318' },
];

const FONT_SIZES = [
    { name: 'Small', slug: 'small', size: '0.875rem' },
    { name: 'Medium', slug: 'medium', size: '1.25rem' },
    { name: 'Large', slug: 'large', size: '2.25rem' },
    { name: 'Extra Large', slug: 'x-large', size: '2.75rem' },
];

const SPACING_SIZES = [
    { name: 'Small', slug: '30', size: '0.75rem' },
    { name: 'Medium', slug: '40', size: '1rem' },
    { name: 'Large', slug: '50', size: '1.5rem' },
    { name: 'Extra Large', slug: '60', size: '2rem' },
    { name: '2X Large', slug: '70', size: '3rem' },
];

const GRADIENTS = [
    { name: 'Blue to green', slug: 'blue-to-green', gradient: 'linear-gradient(135deg,#315bc7 0%,#047857 100%)' },
    { name: 'Light to slate', slug: 'light-to-slate', gradient: 'linear-gradient(135deg,#ffffff 0%,#d0d5dd 100%)' },
];

const EDITOR_THEME_SETTINGS = {
    alignWide: true,
    supportsLayout: true,
    __unstableIsBlockBasedTheme: false,
    maxWidth: 760,
    imageDefaultSize: 'full',
    imageSizes: [
        { slug: 'thumbnail', name: 'Thumbnail' },
        { slug: 'medium', name: 'Medium' },
        { slug: 'large', name: 'Large' },
        { slug: 'full', name: 'Full Size' },
    ],
    colors: COLORS,
    gradients: GRADIENTS,
    fontSizes: FONT_SIZES,
    disableCustomColors: false,
    disableCustomGradients: false,
    disableCustomFontSizes: false,
    enableCustomLineHeight: true,
    enableCustomSpacing: true,
    enableCustomUnits: ['px', 'em', 'rem', 'vh', 'vw', '%'],
    styles: [
        {
            css: `
                .editor-styles-wrapper {
                    --wp--style--global--content-size: ${CONTENT_SIZE};
                    --wp--style--global--wide-size: ${WIDE_SIZE};
                    --wp--style--block-gap: 1.5rem;
                }
            `,
        },
    ],
    __experimentalFeatures: {
        useRootPaddingAwareAlignments: false,
        layout: {
            contentSize: CONTENT_SIZE,
            wideSize: WIDE_SIZE,
        },
        color: {
            custom: true,
            customGradient: true,
            text: true,
            background: true,
            link: true,
            palette: {
                theme: COLORS,
            },
            gradients: {
                theme: GRADIENTS,
            },
            duotone: {
                theme: [],
                default: [],
            },
        },
        spacing: {
            blockGap: true,
            margin: true,
            padding: true,
            units: ['px', 'em', 'rem', 'vh', 'vw', '%'],
            spacingSizes: {
                theme: SPACING_SIZES,
                default: [],
            },
        },
        typography: {
            customFontSize: true,
            dropCap: true,
            fontSizes: {
                theme: FONT_SIZES,
                default: [],
            },
            fontStyle: true,
            fontWeight: true,
            letterSpacing: true,
            lineHeight: true,
            textColumns: true,
            textDecoration: true,
            textIndent: true,
            textTransform: true,
            writingMode: true,
        },
        border: {
            color: true,
            radius: true,
            style: true,
            width: true,
        },
        dimensions: {
            aspectRatio: true,
            aspectRatios: {
                theme: [],
                default: [],
            },
            height: true,
            minHeight: true,
            minWidth: true,
            width: true,
        },
        shadow: {
            presets: {
                theme: [],
                default: [],
            },
        },
    },
};

function isPlainObject(value) {
    return value !== null && typeof value === 'object' && ! Array.isArray(value);
}

function clonePlain(value) {
    if (Array.isArray(value)) {
        return value.map(clonePlain);
    }

    if (isPlainObject(value)) {
        return Object.fromEntries(Object.entries(value).map(([key, item]) => [key, clonePlain(item)]));
    }

    return value;
}

function deepMerge(target, source) {
    const output = clonePlain(target);

    if (! isPlainObject(source)) {
        return output;
    }

    Object.entries(source).forEach(([key, value]) => {
        if (isPlainObject(value) && isPlainObject(output[key])) {
            output[key] = deepMerge(output[key], value);
            return;
        }

        output[key] = clonePlain(value);
    });

    return output;
}

function presetList(value) {
    if (Array.isArray(value)) {
        return value.filter(isPlainObject);
    }

    if (isPlainObject(value)) {
        return Object.values(value).flatMap(presetList);
    }

    return [];
}

function ensurePath(target, keys) {
    let current = target;

    keys.forEach((key) => {
        if (! isPlainObject(current[key])) {
            current[key] = {};
        }

        current = current[key];
    });

    return current;
}

function assignThemePreset(settings, path, presets) {
    if (! presets.length) {
        return;
    }

    const target = ensurePath(settings.__experimentalFeatures, path);
    target.theme = presets;
    target.default = target.default || [];
}

function applyThemeJsonSettings(baseSettings, themeJson) {
    if (! isPlainObject(themeJson) || ! isPlainObject(themeJson.settings)) {
        return baseSettings;
    }

    const next = deepMerge(baseSettings, {});
    const themeSettings = themeJson.settings;
    next.__experimentalFeatures = deepMerge(next.__experimentalFeatures || {}, themeSettings);

    const color = isPlainObject(themeSettings.color) ? themeSettings.color : {};
    const palette = presetList(color.palette);
    const gradients = presetList(color.gradients);
    const duotones = presetList(color.duotone);

    if (palette.length) {
        next.colors = palette;
        assignThemePreset(next, ['color', 'palette'], palette);
    }

    if (gradients.length) {
        next.gradients = gradients;
        assignThemePreset(next, ['color', 'gradients'], gradients);
    }

    if (duotones.length) {
        assignThemePreset(next, ['color', 'duotone'], duotones);
    }

    if (Object.prototype.hasOwnProperty.call(color, 'custom')) {
        next.disableCustomColors = color.custom === false;
        ensurePath(next.__experimentalFeatures, ['color']).custom = color.custom !== false;
    }

    if (Object.prototype.hasOwnProperty.call(color, 'customGradient')) {
        next.disableCustomGradients = color.customGradient === false;
        ensurePath(next.__experimentalFeatures, ['color']).customGradient = color.customGradient !== false;
    }

    const typography = isPlainObject(themeSettings.typography) ? themeSettings.typography : {};
    const fontSizes = presetList(typography.fontSizes);
    const fontFamilies = presetList(typography.fontFamilies);

    if (fontSizes.length) {
        next.fontSizes = fontSizes;
        assignThemePreset(next, ['typography', 'fontSizes'], fontSizes);
    }

    if (fontFamilies.length) {
        assignThemePreset(next, ['typography', 'fontFamilies'], fontFamilies);
    }

    if (Object.prototype.hasOwnProperty.call(typography, 'customFontSize')) {
        next.disableCustomFontSizes = typography.customFontSize === false;
        ensurePath(next.__experimentalFeatures, ['typography']).customFontSize = typography.customFontSize !== false;
    }

    if (Object.prototype.hasOwnProperty.call(typography, 'lineHeight')) {
        next.enableCustomLineHeight = typography.lineHeight !== false;
    }

    const spacing = isPlainObject(themeSettings.spacing) ? themeSettings.spacing : {};
    const spacingSizes = presetList(spacing.spacingSizes);

    if (spacingSizes.length) {
        assignThemePreset(next, ['spacing', 'spacingSizes'], spacingSizes);
    }

    if (Array.isArray(spacing.units)) {
        next.enableCustomUnits = spacing.units;
        ensurePath(next.__experimentalFeatures, ['spacing']).units = spacing.units;
    }

    const dimensions = isPlainObject(themeSettings.dimensions) ? themeSettings.dimensions : {};
    const aspectRatios = presetList(dimensions.aspectRatios);

    if (aspectRatios.length) {
        assignThemePreset(next, ['dimensions', 'aspectRatios'], aspectRatios);
    }

    const shadow = isPlainObject(themeSettings.shadow) ? themeSettings.shadow : {};
    const shadowPresets = presetList(shadow.presets);

    if (shadowPresets.length) {
        assignThemePreset(next, ['shadow', 'presets'], shadowPresets);
    }

    const layout = isPlainObject(themeSettings.layout) ? themeSettings.layout : {};
    const layoutFeatures = ensurePath(next.__experimentalFeatures, ['layout']);

    if (layout.contentSize) {
        layoutFeatures.contentSize = layout.contentSize;
        next.maxWidth = Number.parseInt(layout.contentSize, 10) || next.maxWidth;
    }

    if (layout.wideSize) {
        layoutFeatures.wideSize = layout.wideSize;
    }

    if (themeJson.css) {
        next.styles = [
            ...(Array.isArray(next.styles) ? next.styles : []),
            { css: themeJson.css },
        ];
    }

    return next;
}

function layoutSizeFromSettings(settings, key, fallback) {
    return settings.__experimentalFeatures?.layout?.[key] || fallback;
}

function codeToken(className, value, key) {
    return <span className={className} key={key}>{value}</span>;
}

function pushPlainToken(tokens, value, key) {
    if (value) {
        tokens.push(codeToken('sgb-token sgb-token--plain', value, key));
    }
}

function highlightJson(value, keyPrefix) {
    const tokens = [];
    const pattern = /("(?:\\.|[^"\\])*")(\s*:)?|(-?\b\d+(?:\.\d+)?(?:[eE][+-]?\d+)?\b)|\b(true|false|null)\b|[{}\[\],:]/g;
    let cursor = 0;
    let index = 0;
    let match;

    while ((match = pattern.exec(value)) !== null) {
        pushPlainToken(tokens, value.slice(cursor, match.index), `${keyPrefix}-json-text-${index}`);

        if (match[1]) {
            tokens.push(codeToken(
                match[2] ? 'sgb-token sgb-token--json-key' : 'sgb-token sgb-token--string',
                match[1],
                `${keyPrefix}-json-string-${index}`,
            ));

            if (match[2]) {
                tokens.push(codeToken('sgb-token sgb-token--punctuation', match[2], `${keyPrefix}-json-colon-${index}`));
            }
        } else if (match[3]) {
            tokens.push(codeToken('sgb-token sgb-token--number', match[3], `${keyPrefix}-json-number-${index}`));
        } else if (match[4]) {
            tokens.push(codeToken('sgb-token sgb-token--literal', match[4], `${keyPrefix}-json-literal-${index}`));
        } else {
            tokens.push(codeToken('sgb-token sgb-token--punctuation', match[0], `${keyPrefix}-json-punctuation-${index}`));
        }

        cursor = pattern.lastIndex;
        index += 1;
    }

    pushPlainToken(tokens, value.slice(cursor), `${keyPrefix}-json-tail`);

    return tokens;
}

function highlightComment(value, keyPrefix) {
    const tokens = [];
    const start = '<!--';
    const end = '-->';
    const body = value.slice(start.length, value.endsWith(end) ? -end.length : undefined);
    const jsonStart = body.indexOf('{');
    const jsonEnd = body.lastIndexOf('}');
    const beforeJson = jsonStart >= 0 ? body.slice(0, jsonStart) : body;
    const json = jsonStart >= 0 && jsonEnd >= jsonStart ? body.slice(jsonStart, jsonEnd + 1) : '';
    const afterJson = json ? body.slice(jsonEnd + 1) : '';

    tokens.push(codeToken('sgb-token sgb-token--comment-delimiter', start, `${keyPrefix}-comment-start`));

    beforeJson.split(/(\/?wp:[^\s{}]+)/g).forEach((part, index) => {
        if (! part) {
            return;
        }

        tokens.push(codeToken(
            /^\/?wp:/.test(part) ? 'sgb-token sgb-token--block-name' : 'sgb-token sgb-token--comment',
            part,
            `${keyPrefix}-comment-body-${index}`,
        ));
    });

    if (json) {
        tokens.push(...highlightJson(json, `${keyPrefix}-comment-json`));
    }

    if (afterJson) {
        tokens.push(codeToken('sgb-token sgb-token--comment', afterJson, `${keyPrefix}-comment-after-json`));
    }

    if (value.endsWith(end)) {
        tokens.push(codeToken('sgb-token sgb-token--comment-delimiter', end, `${keyPrefix}-comment-end`));
    }

    return tokens;
}

function highlightAttributes(value, keyPrefix) {
    const tokens = [];
    const pattern = /([^\s=\/'">]+)(\s*=\s*)?("[^"]*"|'[^']*'|[^\s"'>=]+)?/g;
    let cursor = 0;
    let index = 0;
    let match;

    while ((match = pattern.exec(value)) !== null) {
        pushPlainToken(tokens, value.slice(cursor, match.index), `${keyPrefix}-attr-gap-${index}`);
        tokens.push(codeToken('sgb-token sgb-token--attribute', match[1], `${keyPrefix}-attr-name-${index}`));

        if (match[2]) {
            tokens.push(codeToken('sgb-token sgb-token--punctuation', match[2], `${keyPrefix}-attr-equals-${index}`));
        }

        if (match[3]) {
            tokens.push(codeToken('sgb-token sgb-token--string', match[3], `${keyPrefix}-attr-value-${index}`));
        }

        cursor = pattern.lastIndex;
        index += 1;
    }

    pushPlainToken(tokens, value.slice(cursor), `${keyPrefix}-attr-tail`);

    return tokens;
}

function highlightTag(value, keyPrefix) {
    const match = value.match(/^(<\/?)([A-Za-z][^\s/>]*)([\s\S]*?)(\/?>)$/);

    if (! match) {
        return [codeToken('sgb-token sgb-token--tag', value, `${keyPrefix}-tag`)];
    }

    return [
        codeToken('sgb-token sgb-token--punctuation', match[1], `${keyPrefix}-tag-open`),
        codeToken('sgb-token sgb-token--tag-name', match[2], `${keyPrefix}-tag-name`),
        ...highlightAttributes(match[3], `${keyPrefix}-attrs`),
        codeToken('sgb-token sgb-token--punctuation', match[4], `${keyPrefix}-tag-close`),
    ];
}

function highlightCode(value) {
    const tokens = [];
    const pattern = /<!--[\s\S]*?-->|<\/?[A-Za-z][^>]*?>/g;
    let cursor = 0;
    let index = 0;
    let match;

    while ((match = pattern.exec(value)) !== null) {
        pushPlainToken(tokens, value.slice(cursor, match.index), `plain-${index}`);
        tokens.push(...(match[0].startsWith('<!--')
            ? highlightComment(match[0], `comment-${index}`)
            : highlightTag(match[0], `tag-${index}`)));
        cursor = pattern.lastIndex;
        index += 1;
    }

    pushPlainToken(tokens, value.slice(cursor), 'plain-tail');

    if (value.endsWith('\n')) {
        tokens.push(codeToken('sgb-token sgb-token--plain', ' ', 'trailing-space'));
    }

    return tokens;
}

function sameOriginUrl(value) {
    const url = new URL(value, window.location.origin);

    if (url.host === window.location.host) {
        url.protocol = window.location.protocol;
    }

    return url;
}

const ASSET_TYPE_LABELS = {
    audio: 'Audio',
    file: 'Files',
    image: 'Images',
    video: 'Videos',
    visual: 'Images and videos',
};

const ASSET_TYPE_ACCEPTS = {
    audio: 'audio/*',
    file: '',
    image: 'image/*',
    video: 'video/*',
    visual: 'image/*,video/*',
};

const BLOCK_ASSET_TYPES = {
    'core/audio': 'audio',
    'core/cover': 'visual',
    'core/file': 'file',
    'core/gallery': 'image',
    'core/image': 'image',
    'core/media-text': 'visual',
    'core/video': 'video',
};

function assetKey(asset) {
    return String(asset?.id || asset?.path || asset?.url || asset?.filename || '');
}

function normalizeAllowedTypeValues(allowedTypes = []) {
    return Array.isArray(allowedTypes)
        ? allowedTypes.map((type) => String(type).replace(/^mime:/, '').toLowerCase())
        : allowedTypes
            ? [String(allowedTypes).replace(/^mime:/, '').toLowerCase()]
            : [];
}

function assetFilterFromAllowedTypes(allowedTypes = []) {
    const values = normalizeAllowedTypeValues(allowedTypes);
    const mimeTypes = values.filter((type) => /^[a-z0-9.+-]+\/[a-z0-9.+*-]+$/i.test(type));
    const extensions = values
        .map((type) => type.startsWith('.') ? type.slice(1) : '')
        .filter((extension) => /^[a-z0-9]+$/i.test(extension));
    const accept = values
        .filter((type) => type === '*' || type.endsWith('/*') || type.startsWith('.') || /^[a-z0-9.+-]+\/[a-z0-9.+*-]+$/i.test(type))
        .join(',');

    return { values, mimeTypes, extensions, accept };
}

function typeFromAllowedTypes(allowedTypes = []) {
    const { values, mimeTypes } = assetFilterFromAllowedTypes(allowedTypes);

    if (values.includes('audio')) {
        return 'audio';
    }

    if (values.includes('video')) {
        return values.includes('image') ? 'visual' : 'video';
    }

    if (values.includes('image')) {
        return 'image';
    }

    if (
        values.includes('*')
        || values.includes('file')
        || values.some((type) => type === 'application' || type.startsWith('application/'))
    ) {
        return 'file';
    }

    if (mimeTypes.some((type) => type.startsWith('audio/'))) {
        return 'audio';
    }

    if (mimeTypes.some((type) => type.startsWith('video/'))) {
        return mimeTypes.some((type) => type.startsWith('image/')) ? 'visual' : 'video';
    }

    if (mimeTypes.some((type) => type.startsWith('image/'))) {
        return 'image';
    }

    if (mimeTypes.length) {
        return 'file';
    }

    return null;
}

function acceptForAssetPicker(picker) {
    return picker?.accept || ASSET_TYPE_ACCEPTS[picker?.type] || '';
}

function supportedTypeForBlock(blockName) {
    return BLOCK_ASSET_TYPES[blockName] || null;
}

function parentFolder(path) {
    if (! path || path === '/') {
        return '/';
    }

    const parts = path.split('/').filter(Boolean);
    parts.pop();

    return parts.length ? parts.join('/') : '/';
}

function StatamicMediaUpload({ render, value, ...options }) {
    const open = () => {
        window.StatamicGutenbergOpenMediaPicker?.({
            ...options,
            value,
        });
    };

    if (typeof render === 'function') {
        return render({ open, value });
    }

    return (
        <Button variant="secondary" onClick={open}>
            Select asset
        </Button>
    );
}

function registerStatamicMediaUploadFilter() {
    const scope = typeof window !== 'undefined' ? window : globalThis;

    if (scope.__statamicGutenbergMediaUploadFilterInstalled) {
        return;
    }

    addFilter(
        'editor.MediaUpload',
        'statamic-gutenberg/media-upload',
        () => StatamicMediaUpload,
    );

    scope.__statamicGutenbergMediaUploadFilterInstalled = true;
}

registerStatamicMediaUploadFilter();

export function GutenbergEditor({ value, config, meta = {}, onChange, onValidityChange, variant = 'field' }) {
    if (typeof window !== 'undefined' && meta?.iconsUrl) {
        window.StatamicGutenbergIconsUrl = meta.iconsUrl;
    }

    const allowedBlockTypes = useMemo(
        () => normalizeAllowedBlocks(config, meta),
        [config, meta],
    );

    if (typeof window !== 'undefined') {
        if (meta?.blockRendererUrl) {
            window.StatamicGutenbergBlockRendererUrl = meta.blockRendererUrl;
        }

        window.StatamicGutenbergAllowedBlocks = allowedBlockTypes;
    }

    const rawPatternSettings = isPlainObject(meta?.patterns)
        ? meta.patterns
        : (typeof window !== 'undefined' && isPlainObject(window.StatamicGutenbergPatterns)
            ? window.StatamicGutenbergPatterns
            : {});
    const patternSettings = useMemo(
        () => filterPatternPayload(rawPatternSettings, allowedBlockTypes),
        [rawPatternSettings, allowedBlockTypes],
    );
    const customBlocks = useMemo(() => prepareCustomBlockRegistry(meta?.customBlocks), [meta]);

    if (typeof window !== 'undefined' && isPlainObject(patternSettings)) {
        window.StatamicGutenbergPatterns = patternSettings;
    }

    const initialValue = value || '';
    const lastSerialized = useRef(initialValue);
    const historyRef = useRef({ undo: [], redo: [] });
    const historyCurrentRef = useRef(initialValue);
    const [blocks, setBlocks] = useState(() => (customBlocks.length > 0 ? [] : parseSerialized(value)));
    const [codeValue, setCodeValue] = useState(initialValue);
    const [codeError, setCodeError] = useState('');
    const [editorMode, setEditorMode] = useState('visual');
    const [historyDepths, setHistoryDepths] = useState({ undo: 0, redo: 0 });
    const [isListViewOpen, setListViewOpen] = useState(() => variant === 'fullscreen');
    const [assetQuery, setAssetQuery] = useState('');
    const [assets, setAssets] = useState([]);
    const [assetFolders, setAssetFolders] = useState([]);
    const [assetFolder, setAssetFolder] = useState('/');
    const [assetPicker, setAssetPicker] = useState(null);
    const [selectedAssets, setSelectedAssets] = useState([]);
    const [assetsLoading, setAssetsLoading] = useState(false);
    const [assetsUploading, setAssetsUploading] = useState(false);
    const [customBlocksReady, setCustomBlocksReady] = useState(() => customBlocks.length === 0);
    const mediaPickerCallbackRef = useRef(null);
    const uploadInputRef = useRef(null);
    const editorContentRef = useRef(null);
    const codeHighlightRef = useRef(null);
    const selectedBlock = useSelect(
        (select) => select(blockEditorStore).getSelectedBlock(),
        [],
    );
    const { insertBlocks, updateBlockAttributes } = useDispatch(blockEditorStore);

    useEffect(() => {
        let cancelled = false;

        if (! customBlocks.length) {
            setCustomBlocksReady(true);
            return () => {
                cancelled = true;
            };
        }

        setCustomBlocksReady(false);

        loadCustomBlockAssets(customBlocks).finally(() => {
            if (! cancelled) {
                setCustomBlocksReady(true);
            }
        });

        return () => {
            cancelled = true;
        };
    }, [customBlocks]);

    useEffect(() => {
        const nextValue = value || '';

        if (nextValue !== lastSerialized.current) {
            const validation = validateSerialized(nextValue);

            lastSerialized.current = nextValue;
            historyCurrentRef.current = nextValue;
            historyRef.current = { undo: [], redo: [] };
            setHistoryDepths({ undo: 0, redo: 0 });
            setCodeValue(nextValue);
            setCodeError(validation.valid ? '' : validation.message);

            if (validation.valid && customBlocksReady) {
                setBlocks(parseSerialized(nextValue));
            } else if (! validation.valid) {
                setBlocks([]);
            }
        }
    }, [customBlocksReady, value]);

    useEffect(() => {
        if (! customBlocksReady) {
            return;
        }

        const validation = validateSerialized(lastSerialized.current);

        setCodeError(validation.valid ? '' : validation.message);

        if (validation.valid) {
            setBlocks(parseSerialized(lastSerialized.current));
        }
    }, [customBlocksReady]);

    useEffect(() => {
        onValidityChange?.(codeError === '');
    }, [codeError, onValidityChange]);

    const updateHistoryDepths = useCallback(() => {
        setHistoryDepths({
            undo: historyRef.current.undo.length,
            redo: historyRef.current.redo.length,
        });
    }, []);

    const recordHistory = useCallback((serialized) => {
        const current = historyCurrentRef.current;

        if (serialized === current) {
            return;
        }

        historyRef.current.undo.push(current);

        if (historyRef.current.undo.length > HISTORY_LIMIT) {
            historyRef.current.undo.shift();
        }

        historyRef.current.redo = [];
        historyCurrentRef.current = serialized;
        updateHistoryDepths();
    }, [updateHistoryDepths]);

    const applySerializedValue = useCallback((serialized) => {
        const validation = validateSerialized(serialized);

        historyCurrentRef.current = serialized;
        lastSerialized.current = serialized;
        setCodeValue(serialized);
        setCodeError(validation.valid ? '' : validation.message);

        if (validation.valid) {
            setBlocks(parseSerialized(serialized));
        }

        onChange(serialized);
        updateHistoryDepths();
    }, [onChange, updateHistoryDepths]);

    const commitBlocks = useCallback((nextBlocks) => {
        setBlocks(nextBlocks);
        const serialized = serializeBlocks(nextBlocks);
        recordHistory(serialized);
        lastSerialized.current = serialized;
        setCodeValue(serialized);
        setCodeError('');
        onChange(serialized);
    }, [onChange, recordHistory]);

    const handleCodeChange = useCallback((event) => {
        const serialized = event.target.value;
        const validation = validateSerialized(serialized);

        recordHistory(serialized);
        lastSerialized.current = serialized;
        setCodeValue(serialized);
        setCodeError(validation.valid ? '' : validation.message);
        onChange(serialized);
    }, [onChange, recordHistory]);

    const highlightedCode = useMemo(() => highlightCode(codeValue), [codeValue]);

    const syncCodeHighlightScroll = useCallback((event) => {
        if (! codeHighlightRef.current) {
            return;
        }

        codeHighlightRef.current.scrollTop = event.currentTarget.scrollTop;
        codeHighlightRef.current.scrollLeft = event.currentTarget.scrollLeft;
    }, []);

    const undoEdit = useCallback(() => {
        const previous = historyRef.current.undo.pop();

        if (previous === undefined) {
            updateHistoryDepths();
            return;
        }

        historyRef.current.redo.push(historyCurrentRef.current);
        applySerializedValue(previous);
    }, [applySerializedValue, updateHistoryDepths]);

    const redoEdit = useCallback(() => {
        const next = historyRef.current.redo.pop();

        if (next === undefined) {
            updateHistoryDepths();
            return;
        }

        historyRef.current.undo.push(historyCurrentRef.current);
        applySerializedValue(next);
    }, [applySerializedValue, updateHistoryDepths]);

    const switchEditorMode = useCallback((mode) => {
        if (mode === editorMode) {
            return;
        }

        if (mode === 'code') {
            const serialized = serializeBlocks(blocks);
            lastSerialized.current = serialized;
            setCodeValue(serialized);
            setCodeError('');
        } else {
            const validation = validateSerialized(codeValue);

            if (! validation.valid) {
                setCodeError(validation.message);
                return;
            }

            setCodeError('');
            setBlocks(parseSerialized(codeValue));
        }

        setEditorMode(mode);
    }, [blocks, codeValue, editorMode]);

    const handleEditorKeyDown = useCallback((event) => {
        const key = event.key.toLowerCase();

        if ((! event.metaKey && ! event.ctrlKey) || event.altKey) {
            return;
        }

        if (key === 'z') {
            event.preventDefault();
            event.stopPropagation();

            if (event.shiftKey) {
                redoEdit();
            } else {
                undoEdit();
            }
        }

        if (key === 'y' && ! event.shiftKey) {
            event.preventDefault();
            event.stopPropagation();
            redoEdit();
        }
    }, [redoEdit, undoEdit]);

    const fetchAssets = useCallback(async () => {
        if (! meta.assetsUrl || ! assetPicker) {
            setAssets([]);
            setAssetFolders([]);
            return;
        }

        setAssetsLoading(true);

        try {
            const url = sameOriginUrl(meta.assetsUrl);
            url.searchParams.set('q', assetQuery);
            url.searchParams.set('type', assetPicker.type);
            url.searchParams.set('folder', assetFolder);
            (assetPicker.mimeTypes || []).forEach((mimeType) => {
                url.searchParams.append('mime_types[]', mimeType);
            });
            (assetPicker.extensions || []).forEach((extension) => {
                url.searchParams.append('extensions[]', extension);
            });

            const response = await fetch(url.toString(), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const json = await response.json();
            setAssets(Array.isArray(json.data) ? json.data : []);
            setAssetFolders(Array.isArray(json.folders) ? json.folders : []);
        } catch (error) {
            console.warn('Unable to load Statamic assets.', error);
            setAssets([]);
            setAssetFolders([]);
        } finally {
            setAssetsLoading(false);
        }
    }, [assetFolder, assetPicker, assetQuery, meta.assetsUrl]);

    useEffect(() => {
        if (assetPicker) {
            fetchAssets();
        }
    }, [assetPicker, fetchAssets]);

    const closeAssetPicker = useCallback(() => {
        mediaPickerCallbackRef.current = null;
        setSelectedAssets([]);
        setAssetPicker(null);
    }, []);

    const openAssetPicker = useCallback((options = {}) => {
        const requestedViaMediaUpload = Object.prototype.hasOwnProperty.call(options, 'allowedTypes')
            || typeof options.onSelect === 'function';
        const isSelectedGallery = ! requestedViaMediaUpload && selectedBlock?.name === 'core/gallery';
        const type = typeFromAllowedTypes(options.allowedTypes)
            || supportedTypeForBlock(selectedBlock?.name)
            || (requestedViaMediaUpload ? 'file' : 'image');
        const filter = assetFilterFromAllowedTypes(options.allowedTypes);

        mediaPickerCallbackRef.current = typeof options.onSelect === 'function'
            ? {
                onSelect: options.onSelect,
                multiple: Boolean(options.multiple),
            }
            : null;

        setAssets([]);
        setAssetFolders([]);
        setSelectedAssets([]);
        setAssetPicker({
            type,
            accept: filter.accept,
            extensions: filter.extensions,
            mimeTypes: filter.mimeTypes,
            multiple: Boolean(options.multiple || isSelectedGallery),
            title: ASSET_TYPE_LABELS[type] || 'Assets',
        });
    }, [selectedBlock?.name]);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return undefined;
        }

        window.StatamicGutenbergOpenMediaPicker = openAssetPicker;

        return () => {
            if (window.StatamicGutenbergOpenMediaPicker === openAssetPicker) {
                delete window.StatamicGutenbergOpenMediaPicker;
            }
        };
    }, [openAssetPicker]);

    const toggleSelectedAsset = useCallback((asset) => {
        const key = assetKey(asset);

        if (! key) {
            return;
        }

        setSelectedAssets((current) => (
            current.some((selected) => assetKey(selected) === key)
                ? current.filter((selected) => assetKey(selected) !== key)
                : [...current, asset]
        ));
    }, []);

    const insertSelectedAssets = useCallback(() => {
        const callback = mediaPickerCallbackRef.current;

        if (! selectedAssets.length) {
            return;
        }

        if (callback?.multiple) {
            callback.onSelect(selectedAssets.map((asset) => createMediaPayload(asset)));
        } else if (selectedBlock?.name === 'core/gallery') {
            insertBlocks(
                selectedAssets.map((selectedAsset) => createImageBlock(selectedAsset)),
                undefined,
                selectedBlock.clientId,
            );
        } else {
            return;
        }

        closeAssetPicker();
    }, [closeAssetPicker, insertBlocks, selectedAssets, selectedBlock]);

    const selectAsset = useCallback((asset) => {
        const callback = mediaPickerCallbackRef.current;

        if (callback) {
            if (callback.multiple) {
                toggleSelectedAsset(asset);

                return;
            }

            const media = createMediaPayload(asset);
            callback.onSelect(media);
            closeAssetPicker();

            return;
        }

        if (selectedBlock?.name === 'core/gallery') {
            toggleSelectedAsset(asset);

            return;
        }

        if (selectedBlock && supportedTypeForBlock(selectedBlock.name)) {
            updateBlockAttributes(
                selectedBlock.clientId,
                attributesForAssetBlock(selectedBlock.name, asset),
            );
        } else {
            insertBlocks(createAssetBlock(asset));
        }

        closeAssetPicker();
    }, [closeAssetPicker, insertBlocks, selectedBlock, toggleSelectedAsset, updateBlockAttributes]);

    const uploadFiles = useCallback(async (filesList, type = 'image', folder = '/', filter = {}) => {
        if (! meta.uploadUrl) {
            throw new Error('No Statamic upload endpoint configured.');
        }

        const files = Array.from(filesList || []).filter(Boolean);
        const uploaded = [];

        for (const file of files) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('container', meta.assetsContainer || 'assets');
            formData.append('type', type);
            formData.append('folder', folder);
            (filter.mimeTypes || []).forEach((mimeType) => {
                formData.append('mime_types[]', mimeType);
            });
            (filter.extensions || []).forEach((extension) => {
                formData.append('extensions[]', extension);
            });
            const url = sameOriginUrl(meta.uploadUrl);

            const response = await fetch(url.toString(), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: formData,
            });

            const json = await response.json().catch(() => ({}));

            if (! response.ok) {
                const message = json?.message
                    || Object.values(json?.errors || {}).flat().filter(Boolean).join(' ')
                    || `Unable to upload ${file.name}.`;

                throw new Error(message);
            }

            if (json.data) {
                uploaded.push(json.data);
            }
        }

        return uploaded;
    }, [meta.assetsContainer, meta.uploadUrl]);

    const uploadAndSelectAssets = useCallback(async (filesList) => {
        setAssetsUploading(true);

        try {
            const uploaded = await uploadFiles(filesList, assetPicker?.type || 'image', assetFolder, assetPicker || {});

            if (uploaded.length) {
                setAssets((current) => [...uploaded, ...current]);

                const callback = mediaPickerCallbackRef.current;

                if (callback?.multiple) {
                    callback.onSelect(uploaded.map((asset) => createMediaPayload(asset)));
                    closeAssetPicker();

                    return;
                }

                if (selectedBlock?.name === 'core/gallery') {
                    insertBlocks(
                        uploaded.map((asset) => createImageBlock(asset)),
                        undefined,
                        selectedBlock.clientId,
                    );
                    closeAssetPicker();

                    return;
                }

                selectAsset(uploaded[0]);
            }
        } catch (error) {
            console.warn('Unable to upload Statamic asset.', error);
        } finally {
            setAssetsUploading(false);
        }
    }, [assetFolder, assetPicker?.type, closeAssetPicker, insertBlocks, selectAsset, selectedBlock, uploadFiles]);

    const isFullscreen = variant === 'fullscreen';

    const settings = useMemo(() => applyPatternSettings(applyThemeJsonSettings({
        ...EDITOR_THEME_SETTINGS,
        allowedBlockTypes,
        hasFixedToolbar: false,
        inserterMediaCategories: [],
        __experimentalCanUserUseUnfilteredHTML: false,
        mediaUpload: async ({ allowedTypes, filesList, onFileChange, onError }) => {
            try {
                const filter = assetFilterFromAllowedTypes(allowedTypes);
                const uploaded = await uploadFiles(
                    filesList,
                    typeFromAllowedTypes(allowedTypes) || 'file',
                    assetFolder,
                    filter,
                );
                onFileChange?.(uploaded.map((asset) => createMediaPayload(asset)));
                setAssets((current) => [...uploaded, ...current]);
            } catch (error) {
                console.warn('Unable to upload Statamic asset.', error);
                onError?.(error.message);
            }
        },
    }, meta.themeJson), patternSettings), [allowedBlockTypes, assetFolder, uploadFiles, meta.themeJson, patternSettings]);

    const rootBlockLayout = useMemo(() => ({
        ...ROOT_BLOCK_LAYOUT,
        contentSize: layoutSizeFromSettings(settings, 'contentSize', CONTENT_SIZE),
        wideSize: layoutSizeFromSettings(settings, 'wideSize', WIDE_SIZE),
    }), [settings]);

    const toolbar = (
        <div className="sgb-toolbar">
            <div className="sgb-toolbar__group">
                <Inserter
                    rootClientId={undefined}
                    renderToggle={({ onToggle, disabled }) => (
                        <Button
                            icon={plus}
                            label="Add block"
                            disabled={disabled}
                            onClick={onToggle}
                            variant="primary"
                        />
                    )}
                />
                <Button
                    icon={undoIcon}
                    label="Undo"
                    disabled={historyDepths.undo === 0}
                    onClick={undoEdit}
                />
                <Button
                    icon={redoIcon}
                    label="Redo"
                    disabled={historyDepths.redo === 0}
                    onClick={redoEdit}
                />
                {isFullscreen ? (
                    <Button
                        icon={listViewIcon}
                        label="List view"
                        isPressed={isListViewOpen}
                        disabled={editorMode === 'code'}
                        onClick={() => setListViewOpen((isOpen) => ! isOpen)}
                    />
                ) : null}
            </div>
            <div className="sgb-toolbar__group sgb-toolbar__group--end">
                <DropdownMenu
                    icon={moreVertical}
                    label="Options"
                    popoverProps={{ className: 'sgb-options-menu' }}
                    menuProps={{ className: 'sgb-options-menu__menu' }}
                >
                    {({ onClose }) => (
                        <>
                            <MenuGroup label="Editor">
                                <MenuItem
                                    icon={editorMode === 'visual' ? listViewIcon : undefined}
                                    isSelected={editorMode === 'visual'}
                                    role="menuitemradio"
                                    onClick={() => {
                                        switchEditorMode('visual');
                                        onClose();
                                    }}
                                >
                                    Visual editor
                                </MenuItem>
                                <MenuItem
                                    icon={codeIcon}
                                    isSelected={editorMode === 'code'}
                                    role="menuitemradio"
                                    onClick={() => {
                                        switchEditorMode('code');
                                        onClose();
                                    }}
                                >
                                    Code editor
                                </MenuItem>
                            </MenuGroup>
                        </>
                    )}
                </DropdownMenu>
            </div>
        </div>
    );

    const selectedAssetKeys = useMemo(
        () => new Set(selectedAssets.map((asset) => assetKey(asset))),
        [selectedAssets],
    );
    const isMultipleAssetPicker = Boolean(mediaPickerCallbackRef.current?.multiple || assetPicker?.multiple);

    const assetBrowser = assetPicker ? (
        <div className="sgb-assets-modal" role="dialog" aria-modal="true" aria-label="Statamic assets">
            <div className="sgb-assets-modal__panel">
                <div className="sgb-assets-modal__header">
                    <div>
                        <strong>Statamic assets</strong>
                        <span>{assetPicker.title}</span>
                    </div>
                    <Button onClick={closeAssetPicker} variant="secondary">
                        Close
                    </Button>
                </div>
                <div className="sgb-assets__bar">
                    <TextControl
                        label="Search assets"
                        hideLabelFromVision
                        placeholder="Search assets"
                        value={assetQuery}
                        __next40pxDefaultSize
                        onChange={setAssetQuery}
                    />
                    <div className="sgb-assets__actions">
                        {isMultipleAssetPicker ? (
                            <Button
                                disabled={! selectedAssets.length}
                                onClick={insertSelectedAssets}
                                variant="primary"
                            >
                                Insert selected ({selectedAssets.length})
                            </Button>
                        ) : null}
                        <input
                            ref={uploadInputRef}
                            type="file"
                            accept={acceptForAssetPicker(assetPicker)}
                            multiple
                            className="sgb-assets__file-input"
                            onChange={(event) => {
                                uploadAndSelectAssets(event.target.files);
                                event.target.value = '';
                            }}
                        />
                        <Button
                            icon={uploadIcon}
                            label="Upload asset"
                            disabled={assetsUploading}
                            onClick={() => uploadInputRef.current?.click()}
                            variant="primary"
                        />
                        <Button icon={refreshIcon} label="Refresh assets" onClick={fetchAssets} />
                    </div>
                </div>
                <div className="sgb-assets__browser">
                    <aside className="sgb-assets__folders" aria-label="Asset folders">
                        <button
                            type="button"
                            className={assetFolder === '/' ? 'is-active' : ''}
                            onClick={() => setAssetFolder('/')}
                        >
                            Assets
                        </button>
                        {assetFolder !== '/' ? (
                            <button type="button" onClick={() => setAssetFolder(parentFolder(assetFolder))}>
                                Up one folder
                            </button>
                        ) : null}
                        {assetFolders.map((folder) => (
                            <button
                                type="button"
                                key={folder.path}
                                className={assetFolder === folder.path ? 'is-active' : ''}
                                onClick={() => setAssetFolder(folder.path)}
                            >
                                {folder.basename || folder.title}
                            </button>
                        ))}
                    </aside>
                    <section className="sgb-assets__content">
                        {assetsUploading ? (
                            <div className="sgb-assets__empty"><Spinner /> Uploading asset...</div>
                        ) : assetsLoading ? (
                            <div className="sgb-assets__empty"><Spinner /></div>
                        ) : assets.length ? (
                            <div className="sgb-assets__grid">
                                {assets.map((asset, index) => {
                                    const key = assetKey(asset);
                                    const isSelected = key ? selectedAssetKeys.has(key) : false;

                                    return (
                                        <button
                                            type="button"
                                            aria-pressed={isMultipleAssetPicker ? isSelected : undefined}
                                            className={`sgb-asset${isSelected ? ' is-selected' : ''}`}
                                            key={key || `asset-${index}`}
                                            onClick={() => selectAsset(asset)}
                                        >
                                            {asset.media_type === 'image' ? (
                                                <img src={asset.thumbnail || asset.url} alt={asset.alt || asset.filename} />
                                            ) : (
                                                <span className="sgb-asset__file">{asset.extension || asset.media_type}</span>
                                            )}
                                            <span>{asset.filename}</span>
                                        </button>
                                    );
                                })}
                            </div>
                        ) : (
                            <div className="sgb-assets__empty">No matching assets found.</div>
                        )}
                    </section>
                </div>
            </div>
        </div>
    ) : null;
    const themeJsonCss = typeof meta.themeJson?.css === 'string' ? meta.themeJson.css : '';
    const themeJsonSvgs = typeof meta.themeJson?.svgs === 'string' ? meta.themeJson.svgs : '';

    if (! customBlocksReady) {
        return (
            <SlotFillProvider>
                <div className={`sgb-editor sgb-editor--${variant}`}>
                    <div className="sgb-assets__empty"><Spinner /> Loading custom blocks...</div>
                </div>
            </SlotFillProvider>
        );
    }

    return (
        <SlotFillProvider>
            <div
                className={`sgb-editor sgb-editor--${variant} sgb-editor--mode-${editorMode}`}
                onKeyDownCapture={handleEditorKeyDown}
            >
                {themeJsonSvgs ? (
                    <div
                        className="sgb-duotone-filters"
                        dangerouslySetInnerHTML={{ __html: themeJsonSvgs }}
                    />
                ) : null}
                {themeJsonCss ? (
                    <style data-statamic-gutenberg-theme-json>{themeJsonCss}</style>
                ) : null}
                <BlockEditorProvider
                    value={blocks}
                    onInput={commitBlocks}
                    onChange={commitBlocks}
                    settings={settings}
                >
                    <BlockEditorKeyboardShortcuts />
                    {toolbar}
                    {assetBrowser}
                    <div
                        className={[
                            'sgb-editor__workspace',
                            isFullscreen && isListViewOpen && editorMode === 'visual' ? 'sgb-editor__workspace--list-open' : '',
                            editorMode === 'code' ? 'sgb-editor__workspace--code' : '',
                        ].filter(Boolean).join(' ')}
                    >
                        {isFullscreen && isListViewOpen && editorMode === 'visual' ? (
                            <aside className="sgb-list-view">
                                <section className="sgb-list-view__section">
                                    <h2>List view</h2>
                                    <ListView />
                                </section>
                            </aside>
                        ) : null}
                        <main className={`sgb-editor__stage${editorMode === 'code' ? ' sgb-editor__stage--code' : ''}`}>
                            {editorMode === 'code' ? (
                                <div className="sgb-code-editor-shell">
                                    <pre className="sgb-code-highlight" aria-hidden="true" ref={codeHighlightRef}>
                                        <code>{highlightedCode}</code>
                                    </pre>
                                    <textarea
                                        className="sgb-code-editor"
                                        spellCheck={false}
                                        wrap="off"
                                        value={codeValue}
                                        aria-label="Gutenberg code editor"
                                        aria-invalid={codeError ? 'true' : undefined}
                                        aria-describedby={codeError ? 'sgb-code-editor-error' : undefined}
                                        onChange={handleCodeChange}
                                        onScroll={syncCodeHighlightScroll}
                                    />
                                    {codeError ? (
                                        <p className="sgb-code-editor__error" id="sgb-code-editor-error">
                                            {codeError}
                                        </p>
                                    ) : null}
                                </div>
                            ) : (
                                <div className="sgb-page-frame">
                                    <BlockTools __unstableContentRef={editorContentRef}>
                                        <WritingFlow>
                                            <ObserveTyping>
                                                <div className="sgb-canvas" ref={editorContentRef}>
                                                    <BlockList layout={rootBlockLayout} />
                                                </div>
                                            </ObserveTyping>
                                        </WritingFlow>
                                    </BlockTools>
                                </div>
                            )}
                        </main>
                        {isFullscreen && editorMode === 'visual' ? (
                            <aside className="sgb-inspector">
                                <section className="sgb-inspector__section">
                                    <h2>Block settings</h2>
                                    <BlockInspector />
                                </section>
                            </aside>
                        ) : null}
                    </div>
                    <Popover.Slot />
                </BlockEditorProvider>
            </div>
        </SlotFillProvider>
    );
}
