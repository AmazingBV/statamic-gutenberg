import React from 'react';
import { registerCoreBlocks } from '@wordpress/block-library';
import { AlignmentControl, BlockControls } from '@wordpress/block-editor';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { alignCenter, alignJustify, alignLeft, alignRight } from '@wordpress/icons';
import {
    addTextAlignSaveProps,
    isTextFormattingBlock,
    TEXT_ALIGNMENTS,
    withStatamicBlockSupport,
    textAlignClassName,
} from './blockSupport';

let registered = false;
let filtersRegistered = false;

export const TEXT_ALIGNMENT_CONTROLS = [
    {
        icon: alignLeft,
        title: __('Align text left'),
        align: 'left',
    },
    {
        icon: alignCenter,
        title: __('Align text center'),
        align: 'center',
    },
    {
        icon: alignRight,
        title: __('Align text right'),
        align: 'right',
    },
    {
        icon: alignJustify,
        title: __('Justify text'),
        align: 'justify',
    },
];

export function registerGutenbergBlocks() {
    if (registered) {
        return;
    }

    registerStatamicBlockFilters();

    if (typeof window !== 'undefined') {
        window.__experimentalEnableBlockExperiments = true;
        window.__experimentalEnableFormBlocks = true;
    }

    registerCoreBlocks();
    registered = true;
}

function cssJustification(value = 'left') {
    switch (value) {
        case 'center':
            return 'center';
        case 'right':
            return 'flex-end';
        case 'space-between':
            return 'space-between';
        default:
            return 'flex-start';
    }
}

function safeLayoutSize(value) {
    if (typeof value !== 'string' && typeof value !== 'number') {
        return '';
    }

    const size = String(value).trim();

    if (! size || /(?:url|expression|javascript|;|{|}|<|>)/i.test(size)) {
        return '';
    }

    return /^[a-z0-9_.,%()+\-*\/\s]+$/i.test(size) ? size : '';
}

export function withStatamicLayoutWrapperStyles(BlockListBlock) {
    return function StatamicLayoutBlockListBlock(props) {
        const layout = props.attributes?.layout || {};
        const wrapperProps = props.wrapperProps || {};
        const style = { ...(wrapperProps.style || {}) };
        let hasStyleChange = false;

        if (props.name === 'core/group' && layout.type === 'flex') {
            style.display = 'flex';
            style.alignItems = 'flex-start';
            style.justifyContent = cssJustification(layout.justifyContent || layout.contentJustification || 'left');
            style.gap = style.gap || 'var(--wp--style--block-gap)';
            style.flexDirection = layout.orientation === 'vertical' ? 'column' : 'row';
            style.flexWrap = layout.flexWrap === 'nowrap' ? 'nowrap' : 'wrap';
            hasStyleChange = true;
        }

        if (props.name === 'core/group' && layout.type === 'grid') {
            const columnCount = Number.parseInt(layout.columnCount, 10);
            const minimumColumnWidth = safeLayoutSize(layout.minimumColumnWidth);

            style.display = 'grid';
            style.alignItems = 'flex-start';
            style.gap = style.gap || 'var(--wp--style--block-gap)';

            if (columnCount > 0) {
                style.gridTemplateColumns = `repeat(${Math.min(12, columnCount)}, minmax(0, 1fr))`;
            } else if (minimumColumnWidth) {
                style.gridTemplateColumns = `repeat(auto-fill, minmax(min(${minimumColumnWidth}, 100%), 1fr))`;
            } else {
                style.gridTemplateColumns = 'repeat(auto-fill, minmax(min(12rem, 100%), 1fr))';
            }

            hasStyleChange = true;
        }

        const textAlign = props.attributes?.style?.typography?.textAlign;

        if (isTextFormattingBlock(props.name) && TEXT_ALIGNMENTS.includes(textAlign)) {
            style.textAlign = textAlign;
            hasStyleChange = true;
        }

        if (! hasStyleChange) {
            return <BlockListBlock {...props} />;
        }

        const wrapperClassNames = new Set(String(wrapperProps.className || '').split(/\s+/).filter(Boolean));
        const alignClassName = textAlignClassName(props.attributes);

        if (alignClassName) {
            wrapperClassNames.add(alignClassName);
        }

        return (
            <BlockListBlock
                {...props}
                wrapperProps={{
                    ...wrapperProps,
                    className: wrapperClassNames.size ? [...wrapperClassNames].join(' ') : wrapperProps.className,
                    style,
                }}
            />
        );
    };
}

function cleanStyleObject(style) {
    if (! style || typeof style !== 'object') {
        return undefined;
    }

    const cleaned = Object.fromEntries(
        Object.entries(style)
            .map(([key, value]) => [
                key,
                value && typeof value === 'object' && ! Array.isArray(value)
                    ? cleanStyleObject(value)
                    : value,
            ])
            .filter(([, value]) => {
                if (value === undefined || value === null || value === '') {
                    return false;
                }

                return ! (typeof value === 'object' && ! Array.isArray(value) && Object.keys(value).length === 0);
            }),
    );

    return Object.keys(cleaned).length ? cleaned : undefined;
}

export function withStatamicTextAlignmentControls(BlockEdit) {
    return function StatamicTextAlignmentBlockEdit(props) {
        if (! isTextFormattingBlock(props.name)) {
            return <BlockEdit {...props} />;
        }

        const textAlign = props.attributes?.style?.typography?.textAlign;
        const setTextAlign = (nextTextAlign) => {
            const style = {
                ...(props.attributes?.style || {}),
                typography: {
                    ...(props.attributes?.style?.typography || {}),
                    textAlign: nextTextAlign,
                },
            };

            props.setAttributes({
                style: cleanStyleObject(style),
            });
        };

        return (
            <>
                <BlockControls group="block">
                    <AlignmentControl
                        value={textAlign}
                        onChange={setTextAlign}
                        alignmentControls={TEXT_ALIGNMENT_CONTROLS}
                    />
                </BlockControls>
                <BlockEdit {...props} />
            </>
        );
    };
}

function registerStatamicBlockFilters() {
    if (filtersRegistered) {
        return;
    }

    addFilter(
        'blocks.registerBlockType',
        'statamic-gutenberg/block-support',
        withStatamicBlockSupport,
    );

    addFilter(
        'blocks.getSaveContent.extraProps',
        'statamic-gutenberg/text-align-save-props',
        addTextAlignSaveProps,
    );

    addFilter(
        'editor.BlockEdit',
        'statamic-gutenberg/text-alignment-controls',
        withStatamicTextAlignmentControls,
    );

    addFilter(
        'editor.BlockListBlock',
        'statamic-gutenberg/layout-wrapper-styles',
        withStatamicLayoutWrapperStyles,
    );

    filtersRegistered = true;
}
