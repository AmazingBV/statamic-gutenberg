const TEXT_FIELD_TYPES = new Set(['text', 'slug', 'hidden']);
const TEXTAREA_FIELD_TYPES = new Set(['textarea', 'markdown']);
const SELECT_FIELD_TYPES = new Set(['select', 'button_group']);
const NUMBER_FIELD_TYPES = new Set(['integer', 'float', 'range']);
const JSON_FALLBACK_FIELD_TYPES = new Set(['entries', 'terms', 'users']);

export function normalizeBardBlocks(bardBlocks = []) {
    return Array.isArray(bardBlocks)
        ? bardBlocks.filter((block) => block && typeof block.name === 'string' && block.metadata && Array.isArray(block.fields))
        : [];
}

export function controlKindForField(field = {}, value = undefined) {
    if (Array.isArray(value) || (value && typeof value === 'object')) {
        return 'json';
    }

    const type = String(field.type || 'text');

    if (TEXT_FIELD_TYPES.has(type)) {
        return 'text';
    }

    if (TEXTAREA_FIELD_TYPES.has(type)) {
        return 'textarea';
    }

    if (type === 'toggle') {
        return 'toggle';
    }

    if (SELECT_FIELD_TYPES.has(type)) {
        return 'select';
    }

    if (NUMBER_FIELD_TYPES.has(type)) {
        return 'number';
    }

    if (type === 'assets') {
        return 'assets';
    }

    if (JSON_FALLBACK_FIELD_TYPES.has(type)) {
        return 'json';
    }

    return typeof field.default === 'object' && field.default !== null ? 'json' : 'textarea';
}

export function formatBardFieldValue(value) {
    if (Array.isArray(value) || (value && typeof value === 'object')) {
        return JSON.stringify(value, null, 2);
    }

    if (value === undefined || value === null) {
        return '';
    }

    return String(value);
}

export function parseBardFieldValue(value, previousValue) {
    if (Array.isArray(previousValue) || (previousValue && typeof previousValue === 'object')) {
        try {
            return JSON.parse(value);
        } catch {
            return value;
        }
    }

    const trimmed = typeof value === 'string' ? value.trim() : '';

    if ((trimmed.startsWith('{') && trimmed.endsWith('}')) || (trimmed.startsWith('[') && trimmed.endsWith(']'))) {
        try {
            return JSON.parse(trimmed);
        } catch {
            return value;
        }
    }

    return value;
}
