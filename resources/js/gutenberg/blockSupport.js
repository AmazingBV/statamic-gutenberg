export const WIDE_FULL_ALIGNMENTS = ['wide', 'full'];
export const TEXT_FORMATTING_BLOCKS = ['core/paragraph', 'core/heading'];
export const TEXT_SUPPORT_BLOCKS = [
    'core/paragraph',
    'core/heading',
    'core/list',
    'core/list-item',
    'core/quote',
    'core/pullquote',
    'core/preformatted',
    'core/verse',
    'core/code',
    'core/details',
    'core/math',
];
export const CONTAINER_SUPPORT_BLOCKS = [
    'core/group',
    'core/columns',
    'core/column',
    'core/buttons',
    'core/button',
    'core/cover',
    'core/media-text',
    'core/accordion',
    'core/accordion-item',
    'core/accordion-heading',
    'core/accordion-panel',
    'core/tabs',
    'core/tab-list',
    'core/tab-panels',
    'core/tab',
    'core/tab-panel',
];
export const MEDIA_SUPPORT_BLOCKS = [
    'core/audio',
    'core/cover',
    'core/embed',
    'core/file',
    'core/gallery',
    'core/image',
    'core/media-text',
    'core/video',
];
export const UTILITY_SUPPORT_BLOCKS = [
    'core/icon',
    'core/separator',
    'core/spacer',
    'core/table',
];
export const STATAMIC_SUPPORT_BLOCKS = [
    ...new Set([
        ...TEXT_SUPPORT_BLOCKS,
        ...CONTAINER_SUPPORT_BLOCKS,
        ...MEDIA_SUPPORT_BLOCKS,
        ...UTILITY_SUPPORT_BLOCKS,
    ]),
];
export const TEXT_ALIGNMENTS = ['left', 'center', 'right', 'justify'];
export const STATAMIC_MEDIA_IDENTITY_BLOCKS = [
    'core/audio',
    'core/cover',
    'core/file',
    'core/image',
    'core/media-text',
    'core/video',
];

const STYLE_ATTRIBUTE = { type: 'object' };
const STRING_ATTRIBUTE = { type: 'string' };
const ALIGN_ATTRIBUTE = {
    type: 'string',
    enum: ['left', 'center', 'right', 'wide', 'full', ''],
};

const COLOR_SUPPORT = {
    text: true,
    background: true,
    gradients: true,
    link: true,
};

const TYPOGRAPHY_SUPPORT = {
    fontSize: true,
    lineHeight: true,
    textAlign: true,
    textColumns: true,
    textIndent: true,
    fontFamily: true,
    fontStyle: true,
    fontWeight: true,
    letterSpacing: true,
    textDecoration: true,
    textTransform: true,
    writingMode: true,
    __experimentalFontFamily: true,
    __experimentalFontStyle: true,
    __experimentalFontWeight: true,
    __experimentalLetterSpacing: true,
    __experimentalTextDecoration: true,
    __experimentalTextTransform: true,
    __experimentalWritingMode: true,
};

const SPACING_SUPPORT = {
    margin: true,
    padding: true,
    blockGap: true,
};

const BORDER_SUPPORT = {
    color: true,
    radius: true,
    style: true,
    width: true,
};

const DIMENSIONS_SUPPORT = {
    aspectRatio: true,
    height: true,
    minHeight: true,
    minWidth: true,
    width: true,
};

const BACKGROUND_SUPPORT = {
    backgroundImage: true,
    backgroundSize: true,
    backgroundPosition: true,
    backgroundRepeat: true,
};

const TEXT_PROFILE = {
    align: WIDE_FULL_ALIGNMENTS,
    anchor: true,
    color: COLOR_SUPPORT,
    typography: TYPOGRAPHY_SUPPORT,
    spacing: SPACING_SUPPORT,
    __experimentalBorder: BORDER_SUPPORT,
    dimensions: {
        minHeight: true,
    },
    background: BACKGROUND_SUPPORT,
};

const CONTAINER_PROFILE = {
    align: WIDE_FULL_ALIGNMENTS,
    anchor: true,
    className: true,
    color: COLOR_SUPPORT,
    typography: TYPOGRAPHY_SUPPORT,
    spacing: SPACING_SUPPORT,
    __experimentalBorder: BORDER_SUPPORT,
    dimensions: DIMENSIONS_SUPPORT,
    background: BACKGROUND_SUPPORT,
    shadow: true,
    layout: true,
    allowedBlocks: true,
};

const MEDIA_PROFILE = {
    align: WIDE_FULL_ALIGNMENTS,
    anchor: true,
    color: {
        background: true,
        gradients: true,
        link: true,
    },
    spacing: {
        margin: true,
        padding: true,
    },
    __experimentalBorder: BORDER_SUPPORT,
    dimensions: {
        aspectRatio: true,
        height: true,
        minHeight: true,
        width: true,
    },
    shadow: true,
};

const BUTTON_PROFILE = {
    anchor: true,
    color: COLOR_SUPPORT,
    typography: {
        ...TYPOGRAPHY_SUPPORT,
        textAlign: true,
    },
    spacing: {
        padding: true,
        margin: true,
    },
    __experimentalBorder: BORDER_SUPPORT,
    dimensions: {
        width: true,
    },
    shadow: true,
};

const UTILITY_PROFILE = {
    align: WIDE_FULL_ALIGNMENTS,
    anchor: true,
    className: true,
    color: COLOR_SUPPORT,
    typography: TYPOGRAPHY_SUPPORT,
    spacing: SPACING_SUPPORT,
    __experimentalBorder: BORDER_SUPPORT,
    dimensions: DIMENSIONS_SUPPORT,
    shadow: true,
};

function isPlainObject(value) {
    return value !== null && typeof value === 'object' && ! Array.isArray(value);
}

function supportProfileForBlock(name) {
    if (! STATAMIC_SUPPORT_BLOCKS.includes(name)) {
        return null;
    }

    if (name === 'core/button' || name === 'core/tab') {
        return BUTTON_PROFILE;
    }

    if (CONTAINER_SUPPORT_BLOCKS.includes(name)) {
        return CONTAINER_PROFILE;
    }

    if (MEDIA_SUPPORT_BLOCKS.includes(name)) {
        return MEDIA_PROFILE;
    }

    if (UTILITY_SUPPORT_BLOCKS.includes(name)) {
        return UTILITY_PROFILE;
    }

    return TEXT_PROFILE;
}

function mergeSupportValue(current, addition) {
    if (current === false || current === true || addition === undefined) {
        return current;
    }

    if (current === undefined) {
        return addition;
    }

    if (Array.isArray(current) && Array.isArray(addition)) {
        return [...new Set([...current, ...addition])];
    }

    if (Array.isArray(current) && typeof addition === 'string') {
        return current.includes(addition) ? current : [...current, addition];
    }

    if (typeof current === 'string' && Array.isArray(addition)) {
        return [...new Set([current, ...addition])];
    }

    if (isPlainObject(current) && isPlainObject(addition)) {
        return mergeSupportObjects(current, addition);
    }

    return current;
}

function mergeSupportObjects(current = {}, additions = {}) {
    const merged = { ...current };

    Object.entries(additions).forEach(([key, value]) => {
        merged[key] = mergeSupportValue(merged[key], value);
    });

    return merged;
}

function supportIsEnabled(supports, key) {
    const value = supports?.[key];

    return value !== undefined && value !== false;
}

function hasNestedSupport(supports, key) {
    const value = supports?.[key];

    return value === true || isPlainObject(value);
}

function addAttribute(attributes, key, definition) {
    if (attributes[key]?.type) {
        return;
    }

    attributes[key] = definition;
}

function addAttributesForSupports(settings, includeCommonAttributes = false) {
    const supports = settings.supports || {};
    const attributes = { ...(settings.attributes || {}) };
    const hasStyleSupport = [
        'background',
        'color',
        'typography',
        'spacing',
        'border',
        '__experimentalBorder',
        'dimensions',
        'shadow',
        'position',
        'layout',
    ].some((key) => hasNestedSupport(supports, key));

    if (hasStyleSupport) {
        addAttribute(attributes, 'style', STYLE_ATTRIBUTE);
    }

    if (supportIsEnabled(supports, 'align')) {
        addAttribute(attributes, 'align', ALIGN_ATTRIBUTE);
    }

    if (supportIsEnabled(supports, 'anchor')) {
        addAttribute(attributes, 'anchor', STRING_ATTRIBUTE);
    }

    if (includeCommonAttributes || supports.className === true) {
        addAttribute(attributes, 'className', STRING_ATTRIBUTE);
    }

    if (hasNestedSupport(supports, 'color')) {
        addAttribute(attributes, 'textColor', STRING_ATTRIBUTE);
        addAttribute(attributes, 'backgroundColor', STRING_ATTRIBUTE);
        addAttribute(attributes, 'gradient', STRING_ATTRIBUTE);
    }

    if (hasNestedSupport(supports, 'typography')) {
        addAttribute(attributes, 'fontSize', STRING_ATTRIBUTE);
        addAttribute(attributes, 'fontFamily', STRING_ATTRIBUTE);
    }

    if (hasNestedSupport(supports, '__experimentalBorder') || hasNestedSupport(supports, 'border')) {
        addAttribute(attributes, 'borderColor', STRING_ATTRIBUTE);
    }

    return {
        ...settings,
        attributes,
    };
}

export function withWideFullAlignSupport(settings) {
    const supports = { ...(settings.supports || {}) };
    const currentAlign = supports.align;

    const alignments = Array.isArray(currentAlign)
        ? [...currentAlign]
        : typeof currentAlign === 'string'
            ? [currentAlign]
            : [];

    if (currentAlign !== true && currentAlign !== false) {
        WIDE_FULL_ALIGNMENTS.forEach((alignment) => {
            if (! alignments.includes(alignment)) {
                alignments.push(alignment);
            }
        });
    }

    const nextAlign = currentAlign === true || currentAlign === false ? currentAlign : alignments;
    const attributes = { ...(settings.attributes || {}) };

    if (currentAlign !== false && ! attributes.align?.type) {
        attributes.align = ALIGN_ATTRIBUTE;
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

export function withStatamicMediaIdentitySupport(settings, name) {
    if (! STATAMIC_MEDIA_IDENTITY_BLOCKS.includes(name)) {
        return settings;
    }

    const attributes = { ...(settings.attributes || {}) };

    if (! attributes.statamicId?.type) {
        attributes.statamicId = {
            type: 'string',
        };
    }

    return {
        ...settings,
        attributes,
    };
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

    if (typography.textAlign !== true && typography.textAlign !== false) {
        typography.textAlign = Array.isArray(typography.textAlign)
            ? [...new Set([...typography.textAlign, 'justify'])]
            : ['justify'];
    }

    typography.textColumns = true;
    typography.textIndent = true;
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

export function withSupportMatrix(settings, name) {
    const profile = supportProfileForBlock(name);

    if (! profile) {
        return addAttributesForSupports(settings);
    }

    return addAttributesForSupports({
        ...settings,
        supports: mergeSupportObjects(settings.supports || {}, profile),
    }, true);
}

function shouldApplyWideFullAlignSupport(settings, name) {
    return STATAMIC_SUPPORT_BLOCKS.includes(name) || supportIsEnabled(settings.supports || {}, 'align');
}

export function withStatamicBlockSupport(settings, name) {
    const alignedSettings = shouldApplyWideFullAlignSupport(settings, name)
        ? withWideFullAlignSupport(settings)
        : settings;

    return withStatamicMediaIdentitySupport(withTextFormattingSupport(withSupportMatrix(alignedSettings, name), name), name);
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
