import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { getBlockType, getCategories, registerBlockType, setCategories } from '@wordpress/blocks';
import {
    BaseControl,
    Button,
    __experimentalNumberControl as ExperimentalNumberControl,
    PanelBody,
    SelectControl,
    TextControl,
    TextareaControl,
    ToggleControl,
} from '@wordpress/components';
import { createElement, useEffect, useMemo, useState } from '@wordpress/element';
import {
    controlKindForField,
    formatBardFieldValue,
    normalizeBardBlocks,
    parseBardFieldValue,
} from './bardBlockValues';

export {
    controlKindForField,
    formatBardFieldValue,
    normalizeBardBlocks,
    parseBardFieldValue,
};

export function prepareBardBlockRegistry(bardBlocks = [], options = {}) {
    const items = normalizeBardBlocks(bardBlocks);

    if (items.length > 0) {
        ensureBardBlockCategory();
    }

    items.forEach((block) => registerBardBlock(block, options));

    return items;
}

function ensureBardBlockCategory() {
    const categories = Array.isArray(getCategories()) ? getCategories() : [];

    if (categories.some((category) => category?.slug === 'statamic')) {
        return;
    }

    setCategories([
        ...categories,
        {
            slug: 'statamic',
            title: 'Statamic',
        },
    ]);
}

function registerBardBlock(block, options = {}) {
    if (getBlockType(block.name)) {
        return;
    }

    registerBlockType(block.name, {
        ...block.metadata,
        edit: (props) => createElement(BardBlockEdit, {
            ...props,
            block,
            previewUrl: options.previewUrl,
            debounceMs: options.debounceMs,
        }),
        save: () => null,
    });
}

function BardBlockEdit({ attributes = {}, setAttributes, block, previewUrl, debounceMs = 300 }) {
    const values = useMemo(() => ({
        ...(block.defaults || {}),
        ...(isPlainObject(attributes.values) ? attributes.values : {}),
    }), [attributes.values, block.defaults]);
    const blockProps = useBlockProps({ className: 'sgb-bard-block' });
    const preview = useBardPreview(block, values, previewUrl, debounceMs);

    useEffect(() => {
        if (attributes.bardSet !== block.set || attributes.bardSource !== block.source) {
            setAttributes({
                bardSet: block.set,
                bardSource: block.source,
            });
        }
    }, [attributes.bardSet, attributes.bardSource, block.set, block.source, setAttributes]);

    const updateValue = (handle, value) => {
        setAttributes({
            values: {
                ...values,
                [handle]: value,
            },
        });
    };

    return createElement(
        'div',
        blockProps,
        createElement(
            InspectorControls,
            null,
            createElement(
                PanelBody,
                {
                    title: block.metadata?.title || block.name,
                    initialOpen: true,
                },
                block.fields.map((field) => createElement(BardFieldControl, {
                    key: field.handle,
                    field,
                    value: values[field.handle],
                    onChange: (value) => updateValue(field.handle, value),
                })),
            ),
        ),
        createElement(BardPreview, {
            block,
            preview,
        }),
    );
}

function BardFieldControl({ field, value, onChange }) {
    const kind = controlKindForField(field, value);
    const label = field.display || field.handle;
    const help = field.instructions || undefined;

    if (kind === 'toggle') {
        return createElement(ToggleControl, {
            label,
            help,
            checked: Boolean(value),
            onChange,
        });
    }

    if (kind === 'select') {
        return createElement(SelectControl, {
            label,
            help,
            value: value ?? '',
            options: normalizeOptions(field.options),
            onChange,
            __next40pxDefaultSize: true,
        });
    }

    if (kind === 'number') {
        const Control = ExperimentalNumberControl || TextControl;

        return createElement(Control, {
            ...(Control === TextControl ? { type: 'number' } : {}),
            label,
            help,
            value: value ?? '',
            onChange: (nextValue) => onChange(nextValue === '' ? '' : Number(nextValue)),
            __next40pxDefaultSize: true,
        });
    }

    if (kind === 'assets') {
        return createElement(BardAssetControl, {
            field,
            label,
            help,
            value,
            onChange,
        });
    }

    if (kind === 'json') {
        return createElement(TextareaControl, {
            label,
            help,
            value: formatBardFieldValue(value),
            onChange: (nextValue) => onChange(parseBardFieldValue(nextValue, value)),
            __nextHasNoMarginBottom: true,
        });
    }

    if (kind === 'text') {
        return createElement(TextControl, {
            label,
            help,
            value: value ?? '',
            onChange,
            __next40pxDefaultSize: true,
        });
    }

    return createElement(TextareaControl, {
        label,
        help,
        value: value ?? '',
        onChange,
        __nextHasNoMarginBottom: true,
    });
}

function BardAssetControl({ field, label, help, value, onChange }) {
    const multiple = Number(field.maxItems || field.config?.max_items || 0) !== 1;
    const summary = Array.isArray(value)
        ? value.map(assetLabel).filter(Boolean).join(', ')
        : assetLabel(value);

    return createElement(
        BaseControl,
        {
            label,
            help,
            __nextHasNoMarginBottom: true,
        },
        createElement(
            'div',
            { className: 'sgb-bard-assets-control' },
            createElement('p', null, summary || 'No assets selected'),
            createElement(
                Button,
                {
                    variant: 'secondary',
                    onClick: () => {
                        window.StatamicGutenbergOpenMediaPicker?.({
                            multiple,
                            onSelect: (asset) => onChange(multiple ? ensureArray(asset) : asset),
                        });
                    },
                },
                multiple ? 'Choose assets' : 'Choose asset',
            ),
            summary ? createElement(
                Button,
                {
                    variant: 'tertiary',
                    onClick: () => onChange(multiple ? [] : null),
                },
                'Clear',
            ) : null,
        ),
    );
}

function BardPreview({ block, preview }) {
    if (preview.loading) {
        return createElement(
            'div',
            { className: 'sgb-bard-preview sgb-bard-preview--loading' },
            'Loading preview...',
        );
    }

    if (preview.error) {
        return createElement(
            'div',
            { className: 'sgb-bard-preview sgb-bard-preview--error' },
            preview.error,
        );
    }

    if (! preview.html) {
        return createElement(
            'div',
            { className: 'sgb-bard-preview sgb-bard-preview--empty' },
            block.metadata?.title || block.name,
        );
    }

    return createElement('div', {
        className: 'sgb-bard-preview',
        dangerouslySetInnerHTML: { __html: preview.html },
    });
}

function useBardPreview(block, values, previewUrl, debounceMs) {
    const [preview, setPreview] = useState({ html: '', loading: Boolean(previewUrl), error: '' });
    const payload = useMemo(() => ({
        block: block.name,
        set: block.set,
        source: block.source,
        values,
    }), [block.name, block.set, block.source, values]);

    useEffect(() => {
        if (! previewUrl) {
            setPreview({ html: '', loading: false, error: '' });
            return undefined;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(async () => {
            setPreview((current) => ({ ...current, loading: true, error: '' }));

            try {
                const response = await window.fetch(previewUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify(payload),
                    signal: controller.signal,
                });
                const json = await response.json().catch(() => ({}));

                if (! response.ok) {
                    throw new Error(json?.message || 'Unable to render Bard preview.');
                }

                setPreview({ html: json.html || '', loading: false, error: '' });
            } catch (error) {
                if (error.name === 'AbortError') {
                    return;
                }

                setPreview({ html: '', loading: false, error: error.message || 'Unable to render Bard preview.' });
            }
        }, Math.max(0, Number(debounceMs) || 0));

        return () => {
            controller.abort();
            window.clearTimeout(timeout);
        };
    }, [debounceMs, payload, previewUrl]);

    return preview;
}

function normalizeOptions(options = []) {
    if (Array.isArray(options)) {
        return options.map((option) => {
            if (isPlainObject(option)) {
                return {
                    label: option.label || option.text || option.value || '',
                    value: option.value ?? option.label ?? '',
                };
            }

            return { label: String(option), value: String(option) };
        });
    }

    if (isPlainObject(options)) {
        return Object.entries(options).map(([value, label]) => ({
            label: String(label),
            value,
        }));
    }

    return [];
}

function assetLabel(asset) {
    if (! asset) {
        return '';
    }

    if (typeof asset === 'string') {
        return asset;
    }

    return asset.filename || asset.title || asset.id || asset.url || '';
}

function ensureArray(value) {
    return Array.isArray(value) ? value : [value].filter(Boolean);
}

function isPlainObject(value) {
    return value && typeof value === 'object' && ! Array.isArray(value);
}
