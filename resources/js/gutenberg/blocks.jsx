import React from 'react';
import { registerBlockType, getBlockType } from '@wordpress/blocks';
import { registerCoreBlocks } from '@wordpress/block-library';
import { AlignmentControl, BlockControls, useBlockProps, RichText } from '@wordpress/block-editor';
import { TextControl } from '@wordpress/components';
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
    registerStatamicBlocks();
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

const STATAMIC_BUTTON_CLASS = 'wp-block-button__link wp-element-button';
const LEGACY_STATAMIC_BUTTON_CLASS = 'wp-block-button__link';

const STATAMIC_WRAPPER_ATTRIBUTES = {
    align: { type: 'string' },
    anchor: { type: 'string' },
    className: { type: 'string' },
};

const STATAMIC_BLOCK_SUPPORTS = {
    anchor: true,
    customClassName: true,
};

const STATAMIC_DEPRECATED_SUPPORTS = {
    ...STATAMIC_BLOCK_SUPPORTS,
    align: ['wide', 'full'],
};

const STATAMIC_HERO_ATTRIBUTES = {
    ...STATAMIC_WRAPPER_ATTRIBUTES,
    heading: { type: 'string', default: 'Hero heading' },
    text: { type: 'string', default: '' },
    buttonText: { type: 'string', default: '' },
    buttonUrl: { type: 'string', default: '' },
};

const STATAMIC_CTA_ATTRIBUTES = {
    ...STATAMIC_WRAPPER_ATTRIBUTES,
    heading: { type: 'string', default: 'Call to action' },
    text: { type: 'string', default: '' },
    buttonText: { type: 'string', default: 'Learn more' },
    buttonUrl: { type: 'string', default: '#' },
};

function saveStatamicHero({ attributes }, buttonClassName = STATAMIC_BUTTON_CLASS) {
    const blockProps = useBlockProps.save({ className: 'sgb-custom-block sgb-custom-block--hero' });

    return (
        <section {...blockProps}>
            <RichText.Content tagName="h1" value={attributes.heading} />
            {attributes.text ? <RichText.Content tagName="p" value={attributes.text} /> : null}
            {attributes.buttonText && attributes.buttonUrl ? (
                <a className={buttonClassName} href={attributes.buttonUrl}>{attributes.buttonText}</a>
            ) : null}
        </section>
    );
}

function saveStatamicCta({ attributes }, buttonClassName = STATAMIC_BUTTON_CLASS) {
    const blockProps = useBlockProps.save({ className: 'sgb-custom-block sgb-custom-block--cta' });

    return (
        <section {...blockProps}>
            <RichText.Content tagName="h2" value={attributes.heading} />
            {attributes.text ? <RichText.Content tagName="p" value={attributes.text} /> : null}
            {attributes.buttonText && attributes.buttonUrl ? (
                <a className={buttonClassName} href={attributes.buttonUrl}>{attributes.buttonText}</a>
            ) : null}
        </section>
    );
}

function registerStatamicBlocks() {
    if (! getBlockType('statamic/hero')) {
        registerBlockType('statamic/hero', {
            apiVersion: 3,
            title: 'Hero',
            icon: 'cover-image',
            category: 'theme',
            attributes: STATAMIC_HERO_ATTRIBUTES,
            supports: STATAMIC_BLOCK_SUPPORTS,
            edit: ({ attributes, setAttributes }) => {
                const blockProps = useBlockProps({ className: 'sgb-custom-block sgb-custom-block--hero' });

                return (
                    <section {...blockProps}>
                        <div className="sgb-custom-block__content">
                            <RichText
                                tagName="h1"
                                value={attributes.heading}
                                allowedFormats={[]}
                                placeholder="Hero heading"
                                onChange={(heading) => setAttributes({ heading })}
                            />
                            <RichText
                                tagName="p"
                                value={attributes.text}
                                allowedFormats={['core/bold', 'core/italic', 'core/link']}
                                placeholder="Add supporting text..."
                                onChange={(text) => setAttributes({ text })}
                            />
                            <div className="sgb-custom-block__button-edit">
                                <RichText
                                    tagName="span"
                                    className="wp-block-button__link wp-element-button"
                                    value={attributes.buttonText}
                                    allowedFormats={[]}
                                    placeholder="Button text"
                                    onChange={(buttonText) => setAttributes({ buttonText })}
                                />
                                <TextControl
                                    label="Button URL"
                                    value={attributes.buttonUrl}
                                    __next40pxDefaultSize
                                    onChange={(buttonUrl) => setAttributes({ buttonUrl })}
                                />
                            </div>
                        </div>
                    </section>
                );
            },
            save: saveStatamicHero,
            deprecated: [
                {
                    attributes: STATAMIC_HERO_ATTRIBUTES,
                    supports: STATAMIC_DEPRECATED_SUPPORTS,
                    save: (props) => saveStatamicHero(props, LEGACY_STATAMIC_BUTTON_CLASS),
                },
            ],
        });
    }

    if (! getBlockType('statamic/cta')) {
        registerBlockType('statamic/cta', {
            apiVersion: 3,
            title: 'CTA',
            icon: 'megaphone',
            category: 'theme',
            attributes: STATAMIC_CTA_ATTRIBUTES,
            supports: STATAMIC_BLOCK_SUPPORTS,
            edit: ({ attributes, setAttributes }) => {
                const blockProps = useBlockProps({ className: 'sgb-custom-block sgb-custom-block--cta' });

                return (
                    <section {...blockProps}>
                        <div className="sgb-custom-block__content">
                            <RichText
                                tagName="h2"
                                value={attributes.heading}
                                allowedFormats={[]}
                                placeholder="CTA heading"
                                onChange={(heading) => setAttributes({ heading })}
                            />
                            <RichText
                                tagName="p"
                                value={attributes.text}
                                allowedFormats={['core/bold', 'core/italic', 'core/link']}
                                placeholder="Add supporting text..."
                                onChange={(text) => setAttributes({ text })}
                            />
                        </div>
                        <div className="sgb-custom-block__button-edit">
                            <RichText
                                tagName="span"
                                className="wp-block-button__link wp-element-button"
                                value={attributes.buttonText}
                                allowedFormats={[]}
                                placeholder="Button text"
                                onChange={(buttonText) => setAttributes({ buttonText })}
                            />
                            <TextControl
                                label="Button URL"
                                value={attributes.buttonUrl}
                                __next40pxDefaultSize
                                onChange={(buttonUrl) => setAttributes({ buttonUrl })}
                            />
                        </div>
                    </section>
                );
            },
            save: saveStatamicCta,
            deprecated: [
                {
                    attributes: STATAMIC_CTA_ATTRIBUTES,
                    supports: STATAMIC_DEPRECATED_SUPPORTS,
                    save: (props) => saveStatamicCta(props, LEGACY_STATAMIC_BUTTON_CLASS),
                },
            ],
        });
    }
}
