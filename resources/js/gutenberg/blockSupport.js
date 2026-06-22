export const WIDE_FULL_ALIGNMENTS = ['wide', 'full'];

export function withWideFullAlignSupport(settings) {
    const supports = { ...(settings.supports || {}) };
    const currentAlign = supports.align;

    if (currentAlign === true) {
        return settings;
    }

    const alignments = Array.isArray(currentAlign)
        ? [...currentAlign]
        : typeof currentAlign === 'string'
            ? [currentAlign]
            : [];

    WIDE_FULL_ALIGNMENTS.forEach((alignment) => {
        if (! alignments.includes(alignment)) {
            alignments.push(alignment);
        }
    });

    return {
        ...settings,
        supports: {
            ...supports,
            align: alignments,
        },
    };
}
