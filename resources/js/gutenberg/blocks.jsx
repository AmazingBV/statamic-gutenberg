import React from 'react';
import { registerBlockType, getBlockType } from '@wordpress/blocks';
import { registerCoreBlocks } from '@wordpress/block-library';
import { useBlockProps, RichText } from '@wordpress/block-editor';
import { TextControl } from '@wordpress/components';

let registered = false;

export function registerGutenbergBlocks() {
    if (registered) {
        return;
    }

    if (typeof window !== 'undefined') {
        window.__experimentalEnableBlockExperiments = true;
        window.__experimentalEnableFormBlocks = true;
    }

    registerCoreBlocks();
    registerStatamicBlocks();
    registered = true;
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
