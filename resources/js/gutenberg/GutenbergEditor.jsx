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
import { Button, Popover, SlotFillProvider, Spinner, TextControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { addFilter } from '@wordpress/hooks';
import { plus, image as imageIcon, update as refreshIcon, upload as uploadIcon } from '@wordpress/icons';
import { installStatamicApiFetchFallbacks } from './apiFetchFallbacks';
import { registerGutenbergBlocks } from './blocks.jsx';
import {
    attributesForAssetBlock,
    createAssetBlock,
    createMediaPayload,
    normalizeAllowedBlocks,
    parseSerialized,
    serializeBlocks,
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
            palette: {
                theme: COLORS,
            },
            gradients: {
                theme: GRADIENTS,
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
            textDecoration: true,
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
            minHeight: true,
        },
    },
};

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
    'core/image': 'image',
    'core/media-text': 'visual',
    'core/video': 'video',
};

function typeFromAllowedTypes(allowedTypes = []) {
    const values = Array.isArray(allowedTypes)
        ? allowedTypes.map((type) => String(type).replace(/^mime:/, '').toLowerCase())
        : allowedTypes
            ? [String(allowedTypes).replace(/^mime:/, '').toLowerCase()]
        : [];

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

    return null;
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

function StatamicMediaUpload({ allowedTypes, multiple, onSelect, render, value }) {
    const open = () => {
        window.StatamicGutenbergOpenMediaPicker?.({
            allowedTypes,
            multiple,
            onSelect,
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

export function GutenbergEditor({ value, config, meta, onChange, variant = 'field' }) {
    if (typeof window !== 'undefined' && meta?.iconsUrl) {
        window.StatamicGutenbergIconsUrl = meta.iconsUrl;
    }

    const lastSerialized = useRef(value || '');
    const [blocks, setBlocks] = useState(() => parseSerialized(value));
    const [assetQuery, setAssetQuery] = useState('');
    const [assets, setAssets] = useState([]);
    const [assetFolders, setAssetFolders] = useState([]);
    const [assetFolder, setAssetFolder] = useState('/');
    const [assetPicker, setAssetPicker] = useState(null);
    const [assetsLoading, setAssetsLoading] = useState(false);
    const [assetsUploading, setAssetsUploading] = useState(false);
    const mediaPickerCallbackRef = useRef(null);
    const uploadInputRef = useRef(null);
    const editorContentRef = useRef(null);
    const selectedBlock = useSelect(
        (select) => select(blockEditorStore).getSelectedBlock(),
        [],
    );
    const { insertBlocks, updateBlockAttributes } = useDispatch(blockEditorStore);

    const allowedBlockTypes = useMemo(
        () => normalizeAllowedBlocks(config, meta),
        [config, meta],
    );

    useEffect(() => {
        const nextValue = value || '';

        if (nextValue !== lastSerialized.current) {
            lastSerialized.current = nextValue;
            setBlocks(parseSerialized(nextValue));
        }
    }, [value]);

    const commitBlocks = useCallback((nextBlocks) => {
        setBlocks(nextBlocks);
        const serialized = serializeBlocks(nextBlocks);
        lastSerialized.current = serialized;
        onChange(serialized);
    }, [onChange]);

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
        setAssetPicker(null);
    }, []);

    const openAssetPicker = useCallback((options = {}) => {
        const requestedViaMediaUpload = Object.prototype.hasOwnProperty.call(options, 'allowedTypes')
            || typeof options.onSelect === 'function';
        const type = typeFromAllowedTypes(options.allowedTypes)
            || supportedTypeForBlock(selectedBlock?.name)
            || (requestedViaMediaUpload ? 'file' : 'image');

        mediaPickerCallbackRef.current = typeof options.onSelect === 'function'
            ? {
                onSelect: options.onSelect,
                multiple: Boolean(options.multiple),
            }
            : null;

        setAssets([]);
        setAssetFolders([]);
        setAssetPicker({
            type,
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

    const selectAsset = useCallback((asset) => {
        const callback = mediaPickerCallbackRef.current;

        if (callback) {
            const media = createMediaPayload(asset);
            callback.onSelect(callback.multiple ? [media] : media);
            closeAssetPicker();

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
    }, [closeAssetPicker, insertBlocks, selectedBlock, updateBlockAttributes]);

    const uploadFiles = useCallback(async (filesList, type = 'image', folder = '/') => {
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
            const uploaded = await uploadFiles(filesList, assetPicker?.type || 'image', assetFolder);

            if (uploaded.length) {
                setAssets((current) => [...uploaded, ...current]);
                selectAsset(uploaded[0]);
            }
        } catch (error) {
            console.warn('Unable to upload Statamic asset.', error);
        } finally {
            setAssetsUploading(false);
        }
    }, [assetFolder, assetPicker?.type, selectAsset, uploadFiles]);

    const isFullscreen = variant === 'fullscreen';

    const settings = useMemo(() => ({
        ...EDITOR_THEME_SETTINGS,
        allowedBlockTypes,
        hasFixedToolbar: false,
        inserterMediaCategories: [],
        __experimentalCanUserUseUnfilteredHTML: false,
        mediaUpload: async ({ allowedTypes, filesList, onFileChange, onError }) => {
            try {
                const uploaded = await uploadFiles(
                    filesList,
                    typeFromAllowedTypes(allowedTypes) || 'file',
                    assetFolder,
                );
                onFileChange?.(uploaded.map((asset) => createMediaPayload(asset)));
                setAssets((current) => [...uploaded, ...current]);
            } catch (error) {
                console.warn('Unable to upload Statamic asset.', error);
                onError?.(error.message);
            }
        },
    }), [allowedBlockTypes, assetFolder, uploadFiles]);

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
                    icon={imageIcon}
                    label="Open Statamic assets"
                    onClick={() => openAssetPicker()}
                    variant="secondary"
                />
            </div>
        </div>
    );

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
                        <input
                            ref={uploadInputRef}
                            type="file"
                            accept={ASSET_TYPE_ACCEPTS[assetPicker.type] || ''}
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
                                {assets.map((asset) => (
                                    <button
                                        type="button"
                                        className="sgb-asset"
                                        key={asset.id}
                                        onClick={() => selectAsset(asset)}
                                    >
                                        {asset.media_type === 'image' ? (
                                            <img src={asset.thumbnail || asset.url} alt={asset.alt || asset.filename} />
                                        ) : (
                                            <span className="sgb-asset__file">{asset.extension || asset.media_type}</span>
                                        )}
                                        <span>{asset.filename}</span>
                                    </button>
                                ))}
                            </div>
                        ) : (
                            <div className="sgb-assets__empty">No matching assets found.</div>
                        )}
                    </section>
                </div>
            </div>
        </div>
    ) : null;

    return (
        <SlotFillProvider>
            <div className={`sgb-editor sgb-editor--${variant}`}>
                <BlockEditorProvider
                    value={blocks}
                    onInput={commitBlocks}
                    onChange={commitBlocks}
                    settings={settings}
                >
                    <BlockEditorKeyboardShortcuts />
                    {toolbar}
                    {assetBrowser}
                    <div className="sgb-editor__workspace">
                        <main className="sgb-editor__stage">
                            <div className="sgb-page-frame">
                                <BlockTools __unstableContentRef={editorContentRef}>
                                    <WritingFlow>
                                        <ObserveTyping>
                                            <div className="sgb-canvas" ref={editorContentRef}>
                                                <BlockList />
                                            </div>
                                        </ObserveTyping>
                                    </WritingFlow>
                                </BlockTools>
                            </div>
                        </main>
                        {isFullscreen ? (
                            <aside className="sgb-inspector">
                                <section className="sgb-inspector__section">
                                    <h2>Document outline</h2>
                                    <ListView />
                                </section>
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
