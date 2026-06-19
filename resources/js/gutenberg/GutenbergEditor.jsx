import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
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
} from '@wordpress/block-editor';
import { Button, Popover, SlotFillProvider, Spinner, TextControl } from '@wordpress/components';
import { plus, image as imageIcon, update as refreshIcon, upload as uploadIcon } from '@wordpress/icons';
import { registerGutenbergBlocks } from './blocks.jsx';
import {
    createImageMedia,
    createImageBlock,
    normalizeAllowedBlocks,
    parseSerialized,
    serializeBlocks,
} from './serialization';

import '@wordpress/components/build-style/style.css';
import '@wordpress/block-editor/build-style/style.css';
import '@wordpress/block-editor/build-style/content.css';
import '@wordpress/block-library/build-style/editor.css';
import '@wordpress/block-library/build-style/style.css';

registerGutenbergBlocks();

function sameOriginUrl(value) {
    const url = new URL(value, window.location.origin);

    if (url.host === window.location.host) {
        url.protocol = window.location.protocol;
    }

    return url;
}

export function GutenbergEditor({ value, config, meta, onChange, variant = 'field', title = '' }) {
    const lastSerialized = useRef(value || '');
    const [blocks, setBlocks] = useState(() => parseSerialized(value));
    const [assetQuery, setAssetQuery] = useState('');
    const [assets, setAssets] = useState([]);
    const [assetsOpen, setAssetsOpen] = useState(false);
    const [assetsLoading, setAssetsLoading] = useState(false);
    const [assetsUploading, setAssetsUploading] = useState(false);
    const uploadInputRef = useRef(null);

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
        if (! meta.assetsUrl) {
            setAssets([]);
            return;
        }

        setAssetsLoading(true);

        try {
            const url = sameOriginUrl(meta.assetsUrl);
            url.searchParams.set('q', assetQuery);

            const response = await fetch(url.toString(), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const json = await response.json();
            setAssets(Array.isArray(json.data) ? json.data : []);
        } catch (error) {
            console.warn('Unable to load Statamic assets.', error);
            setAssets([]);
        } finally {
            setAssetsLoading(false);
        }
    }, [assetQuery, meta.assetsUrl]);

    useEffect(() => {
        if (assetsOpen) {
            fetchAssets();
        }
    }, [assetsOpen, fetchAssets]);

    const insertAsset = useCallback((asset) => {
        commitBlocks([...blocks, createImageBlock(asset)]);
        setAssetsOpen(false);
    }, [blocks, commitBlocks]);

    const uploadFiles = useCallback(async (filesList) => {
        if (! meta.uploadUrl) {
            throw new Error('No Statamic upload endpoint configured.');
        }

        const files = Array.from(filesList || []).filter(Boolean);
        const uploaded = [];

        for (const file of files) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('container', meta.assetsContainer || 'assets');
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

    const uploadAndInsertAssets = useCallback(async (filesList) => {
        setAssetsUploading(true);

        try {
            const uploaded = await uploadFiles(filesList);

            if (uploaded.length) {
                commitBlocks([...blocks, ...uploaded.map((asset) => createImageBlock(asset))]);
                setAssets((current) => [...uploaded, ...current]);
                setAssetsOpen(false);
            }
        } catch (error) {
            console.warn('Unable to upload Statamic asset.', error);
        } finally {
            setAssetsUploading(false);
        }
    }, [blocks, commitBlocks, uploadFiles]);

    const isFullscreen = variant === 'fullscreen';

    const settings = useMemo(() => ({
        allowedBlockTypes,
        hasFixedToolbar: true,
        __experimentalCanUserUseUnfilteredHTML: false,
        mediaUpload: async ({ filesList, onFileChange, onError }) => {
            try {
                const uploaded = await uploadFiles(filesList);
                onFileChange?.(uploaded.map((asset) => createImageMedia(asset)));
                setAssets((current) => [...uploaded, ...current]);
            } catch (error) {
                console.warn('Unable to upload Statamic asset.', error);
                onError?.(error.message);
            }
        },
    }), [allowedBlockTypes, uploadFiles]);

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
                    label="Insert Statamic asset"
                    onClick={() => setAssetsOpen((open) => ! open)}
                    variant="secondary"
                />
            </div>
        </div>
    );

    const assetPicker = assetsOpen ? (
        <div className="sgb-assets">
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
                        accept="image/*"
                        multiple
                        className="sgb-assets__file-input"
                        onChange={(event) => {
                            uploadAndInsertAssets(event.target.files);
                            event.target.value = '';
                        }}
                    />
                    <Button
                        icon={uploadIcon}
                        label="Upload image"
                        disabled={assetsUploading}
                        onClick={() => uploadInputRef.current?.click()}
                        variant="primary"
                    />
                    <Button icon={refreshIcon} label="Refresh assets" onClick={fetchAssets} />
                </div>
            </div>
            {assetsUploading ? (
                <div className="sgb-assets__empty"><Spinner /> Uploading image...</div>
            ) : assetsLoading ? (
                <div className="sgb-assets__empty"><Spinner /></div>
            ) : assets.length ? (
                <div className="sgb-assets__grid">
                    {assets.map((asset) => (
                        <button
                            type="button"
                            className="sgb-asset"
                            key={asset.id}
                            onClick={() => insertAsset(asset)}
                        >
                            <img src={asset.url} alt={asset.alt || asset.filename} />
                            <span>{asset.filename}</span>
                        </button>
                    ))}
                </div>
            ) : (
                <div className="sgb-assets__empty">No image assets found.</div>
            )}
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
                    {assetPicker}
                    <div className="sgb-editor__workspace">
                        <main className="sgb-editor__stage">
                            <div className="sgb-page-frame">
                                {isFullscreen && title ? (
                                    <h1 className="sgb-page-title">{title}</h1>
                                ) : null}
                                <BlockTools>
                                    <WritingFlow>
                                        <ObserveTyping>
                                            <div className="sgb-canvas">
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
