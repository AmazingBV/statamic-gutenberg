import React from 'react';
import { registerBlockType, getBlockType } from '@wordpress/blocks';
import { registerCoreBlocks } from '@wordpress/block-library';
import { useBlockProps, RichText } from '@wordpress/block-editor';
import { TextControl } from '@wordpress/components';
import { addFilter } from '@wordpress/hooks';
import { withWideFullAlignSupport } from './blockSupport';

let registered = false;
let filtersRegistered = false;

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

export function withStatamicLayoutWrapperStyles(BlockListBlock) {
    return function StatamicLayoutBlockListBlock(props) {
        const layout = props.attributes?.layout || {};
        const wrapperProps = props.wrapperProps || {};
        const style = { ...(wrapperProps.style || {}) };
        let hasStyleChange = false;

        if (props.name === 'core/group' && layout.type === 'flex') {
            style.display = 'flex';
            style.alignItems = 'flex-start';
            style.gap = style.gap || 'var(--wp--style--block-gap)';
            style.flexDirection = layout.orientation === 'vertical' ? 'column' : 'row';
            style.flexWrap = layout.flexWrap === 'nowrap' ? 'nowrap' : 'wrap';
            hasStyleChange = true;
        }

        if (props.name === 'core/group' && layout.type === 'grid') {
            const columnCount = Number.parseInt(layout.columnCount, 10);
            const minimumColumnWidth = typeof layout.minimumColumnWidth === 'string'
                ? layout.minimumColumnWidth.trim()
                : '';

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

        if (! hasStyleChange) {
            return <BlockListBlock {...props} />;
        }

        return (
            <BlockListBlock
                {...props}
                wrapperProps={{
                    ...wrapperProps,
                    style,
                }}
            />
        );
    };
}

function registerStatamicBlockFilters() {
    if (filtersRegistered) {
        return;
    }

    addFilter(
        'blocks.registerBlockType',
        'statamic-gutenberg/wide-full-align-support',
        withWideFullAlignSupport,
    );

    addFilter(
        'editor.BlockListBlock',
        'statamic-gutenberg/layout-wrapper-styles',
        withStatamicLayoutWrapperStyles,
    );

    filtersRegistered = true;
}

function registerStatamicBlocks() {
    if (! getBlockType('statamic/hero')) {
        registerBlockType('statamic/hero', {
            apiVersion: 3,
            title: 'Hero',
            icon: 'cover-image',
            category: 'theme',
            attributes: {
                heading: { type: 'string', default: 'Hero heading' },
                text: { type: 'string', default: '' },
                buttonText: { type: 'string', default: '' },
                buttonUrl: { type: 'string', default: '' },
            },
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
                                    className="sgb-button"
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
            save: ({ attributes }) => {
                const blockProps = useBlockProps.save({ className: 'sgb-custom-block sgb-custom-block--hero' });

                return (
                    <section {...blockProps}>
                        <RichText.Content tagName="h1" value={attributes.heading} />
                        {attributes.text ? <p>{attributes.text}</p> : null}
                        {attributes.buttonText && attributes.buttonUrl ? (
                            <a className="wp-block-button__link" href={attributes.buttonUrl}>{attributes.buttonText}</a>
                        ) : null}
                    </section>
                );
            },
        });
    }

    if (! getBlockType('statamic/cta')) {
        registerBlockType('statamic/cta', {
            apiVersion: 3,
            title: 'CTA',
            icon: 'megaphone',
            category: 'theme',
            attributes: {
                heading: { type: 'string', default: 'Call to action' },
                text: { type: 'string', default: '' },
                buttonText: { type: 'string', default: 'Learn more' },
                buttonUrl: { type: 'string', default: '#' },
            },
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
                                className="sgb-button"
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
            save: ({ attributes }) => {
                const blockProps = useBlockProps.save({ className: 'sgb-custom-block sgb-custom-block--cta' });

                return (
                    <section {...blockProps}>
                        <RichText.Content tagName="h2" value={attributes.heading} />
                        {attributes.text ? <p>{attributes.text}</p> : null}
                        {attributes.buttonText && attributes.buttonUrl ? (
                            <a className="wp-block-button__link" href={attributes.buttonUrl}>{attributes.buttonText}</a>
                        ) : null}
                    </section>
                );
            },
        });
    }
}
