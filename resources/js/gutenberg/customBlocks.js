import apiFetch from '@wordpress/api-fetch';
import * as blockEditor from '@wordpress/block-editor';
import * as blocks from '@wordpress/blocks';
import * as components from '@wordpress/components';
import * as data from '@wordpress/data';
import * as element from '@wordpress/element';
import * as hooks from '@wordpress/hooks';
import * as i18n from '@wordpress/i18n';
import * as richText from '@wordpress/rich-text';
import * as url from '@wordpress/url';
import * as React from 'react';
import * as ReactJSXRuntime from 'react/jsx-runtime';

const SCRIPT_REGISTRY_KEY = '__statamicGutenbergCustomBlockScripts';
const STYLE_SELECTOR = 'link[data-sgb-custom-block-style]';
const BLOCKS_WRAPPED_KEY = '__statamicGutenbergBlocksWrapped';

export function loadCustomBlockAssets(customBlocks = []) {
    const items = normalizeCustomBlocks(customBlocks);

    exposeWordPressGlobals(items);
    appendStyles(items.flatMap((block) => block.editorStyles || []));

    return Promise.all(items.flatMap((block) => block.editorScripts || []).map(loadScriptAsset))
        .catch((error) => {
            console.warn('Unable to load custom block assets.', error);
        })
        .then(() => {
            registerFallbackBlocks(items);
        });
}

export function normalizeCustomBlocks(customBlocks = []) {
    return Array.isArray(customBlocks)
        ? customBlocks.filter((block) => block && typeof block.name === 'string' && block.metadata)
        : [];
}

function exposeWordPressGlobals(customBlocks) {
    if (typeof window === 'undefined') {
        return;
    }

    const wp = window.wp || {};
    const blockApi = wp.blocks || blocks;

    wp.apiFetch = wp.apiFetch || apiFetch;
    wp.blockEditor = wp.blockEditor || blockEditor;
    wp.blocks = wrapBlocksApi(blockApi);
    wp.components = wp.components || components;
    wp.data = wp.data || data;
    wp.element = wp.element || element;
    wp.hooks = wp.hooks || hooks;
    wp.i18n = wp.i18n || i18n;
    wp.richText = wp.richText || richText;
    wp.url = wp.url || url;
    window.wp = wp;
    window.React = window.React || React;
    window.ReactJSXRuntime = window.ReactJSXRuntime || ReactJSXRuntime;

    const byName = Object.fromEntries(customBlocks.map((block) => [block.name, block]));
    window.StatamicGutenbergCustomBlocks = {
        ...(window.StatamicGutenbergCustomBlocks || {}),
        ...byName,
    };

    window.StatamicGutenberg = {
        ...(window.StatamicGutenberg || {}),
        registerBlockType(name, settings = {}) {
            if (wp.blocks.getBlockType(name)) {
                return wp.blocks.getBlockType(name);
            }

            const metadata = window.StatamicGutenbergCustomBlocks?.[name]?.metadata || { name };

            return wp.blocks.registerBlockType(name, settings);
        },
    };
}

function wrapBlocksApi(blockApi) {
    if (blockApi?.[BLOCKS_WRAPPED_KEY]) {
        return blockApi;
    }

    const registerBlockType = blockApi.registerBlockType.bind(blockApi);

    return {
        ...blockApi,
        [BLOCKS_WRAPPED_KEY]: true,
        registerBlockType(nameOrMetadata, settings = {}) {
            const name = typeof nameOrMetadata === 'string'
                ? nameOrMetadata
                : nameOrMetadata?.name;
            const customMetadata = name
                ? window.StatamicGutenbergCustomBlocks?.[name]?.metadata
                : null;

            if (! customMetadata) {
                return registerBlockType(nameOrMetadata, settings);
            }

            if (typeof nameOrMetadata === 'string') {
                return registerBlockType(nameOrMetadata, mergeBlockSettings(customMetadata, settings));
            }

            return registerBlockType(mergeBlockSettings(customMetadata, nameOrMetadata), settings);
        },
    };
}

function mergeBlockSettings(metadata = {}, settings = {}) {
    return {
        ...metadata,
        ...settings,
        attributes: {
            ...(metadata.attributes || {}),
            ...(settings.attributes || {}),
        },
        supports: {
            ...(metadata.supports || {}),
            ...(settings.supports || {}),
        },
    };
}

function appendStyles(styles) {
    if (typeof document === 'undefined') {
        return;
    }

    const existing = new Set(
        Array.from(document.querySelectorAll(STYLE_SELECTOR))
            .map((link) => link.getAttribute('href'))
            .filter(Boolean),
    );

    styles
        .filter((style) => typeof style === 'string' && style !== '')
        .forEach((href) => {
            if (existing.has(href)) {
                return;
            }

            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = href;
            link.dataset.sgbCustomBlockStyle = 'true';
            document.head.appendChild(link);
            existing.add(href);
        });
}

function loadScriptAsset(asset) {
    if (typeof document === 'undefined' || ! asset?.src) {
        return Promise.resolve();
    }

    const registry = window[SCRIPT_REGISTRY_KEY] || {};
    window[SCRIPT_REGISTRY_KEY] = registry;

    const key = `${asset.module ? 'module' : 'script'}:${asset.src}`;

    if (registry[key]) {
        return registry[key];
    }

    registry[key] = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = asset.src;
        script.async = false;

        if (asset.module) {
            script.type = 'module';
        }

        script.onload = () => resolve();
        script.onerror = () => reject(new Error(`Unable to load ${asset.src}`));
        document.head.appendChild(script);
    });

    return registry[key];
}

function registerFallbackBlocks(customBlocks) {
    customBlocks.forEach((block) => {
        if (blocks.getBlockType(block.name)) {
            return;
        }

        blocks.registerBlockType(block.name, {
            ...block.metadata,
            edit: CustomBlockPlaceholderEdit,
            save: () => null,
        });
    });
}

function CustomBlockPlaceholderEdit({ name }) {
    const blockType = blocks.getBlockType(name);
    const blockProps = blockEditor.useBlockProps({
        className: 'sgb-custom-block-placeholder',
    });

    return element.createElement(
        'div',
        blockProps,
        element.createElement('strong', null, blockType?.title || name),
        element.createElement('span', null, 'Custom block'),
    );
}
