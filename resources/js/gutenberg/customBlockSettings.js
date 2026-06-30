function isPlainObject(value) {
    return value && typeof value === 'object' && ! Array.isArray(value);
}

function mergeNestedObjects(base = {}, override = {}) {
    const merged = { ...base };

    Object.entries(override).forEach(([key, value]) => {
        merged[key] = isPlainObject(value) && isPlainObject(merged[key])
            ? mergeNestedObjects(merged[key], value)
            : value;
    });

    return merged;
}

export function mergeBlockSettings(metadata = {}, settings = {}) {
    return {
        ...metadata,
        ...settings,
        attributes: mergeNestedObjects(metadata.attributes || {}, settings.attributes || {}),
        supports: mergeNestedObjects(metadata.supports || {}, settings.supports || {}),
    };
}
