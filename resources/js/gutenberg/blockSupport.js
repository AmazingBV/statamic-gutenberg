export const WIDE_FULL_ALIGNMENTS = ['wide', 'full'];

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
