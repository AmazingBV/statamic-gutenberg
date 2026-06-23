export const WIDE_FULL_ALIGNMENTS = ['wide', 'full'];
export const TEXT_FORMATTING_BLOCKS = ['core/paragraph', 'core/heading'];
export const TEXT_ALIGNMENTS = ['left', 'center', 'right', 'justify'];

export function withWideFullAlignSupport(settings) {
    const supports = { ...(settings.supports || {}) };
    const currentAlign = supports.align;

    const alignments = Array.isArray(currentAlign)
        ? [...currentAlign]
        : typeof currentAlign === 'string'
            ? [currentAlign]
            : [];

    if (currentAlign !== true) {
        WIDE_FULL_ALIGNMENTS.forEach((alignment) => {
            if (! alignments.includes(alignment)) {
                alignments.push(alignment);
            }
        });
    }

    const nextAlign = currentAlign === true ? true : alignments;
    const attributes = { ...(settings.attributes || {}) };

    if (! attributes.align?.type) {
        attributes.align = {
            type: 'string',
            enum: ['left', 'center', 'right', 'wide', 'full', ''],
        };
    }

    return {
        ...settings,
        attributes,
        supports: {
            ...supports,
            align: nextAlign,
        },
    };
}

export function isTextFormattingBlock(name) {
    return TEXT_FORMATTING_BLOCKS.includes(name);
}

export function withTextFormattingSupport(settings, name) {
    if (! isTextFormattingBlock(name)) {
        return settings;
    }

    const supports = { ...(settings.supports || {}) };
    const typography = typeof supports.typography === 'object' && supports.typography !== null
        ? { ...supports.typography }
        : {};
    const color = typeof supports.color === 'object' && supports.color !== null
        ? { ...supports.color }
        : {};
    const attributes = { ...(settings.attributes || {}) };

    typography.textAlign = ['justify'];
    color.text = true;

    if (! attributes.style?.type) {
        attributes.style = {
            type: 'object',
        };
    }

    return {
        ...settings,
        attributes,
        supports: {
            ...supports,
            typography,
            color,
        },
    };
}

export function withStatamicBlockSupport(settings, name) {
    return withTextFormattingSupport(withWideFullAlignSupport(settings, name), name);
}

export function textAlignClassName(attributes = {}) {
    const textAlign = attributes?.style?.typography?.textAlign;

    if (! TEXT_ALIGNMENTS.includes(textAlign)) {
        return '';
    }

    return `has-text-align-${textAlign}`;
}

export function addTextAlignSaveProps(props, blockType, attributes) {
    const name = typeof blockType === 'string' ? blockType : blockType?.name;
    const className = textAlignClassName(attributes);

    if (! isTextFormattingBlock(name) || ! className) {
        return props;
    }

    const classNames = new Set(String(props.className || '').split(/\s+/).filter(Boolean));
    classNames.add(className);

    return {
        ...props,
        className: [...classNames].join(' '),
    };
}
