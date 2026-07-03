import { registerBlockStyle } from '@wordpress/blocks';

function isPlainObject(value) {
    return value !== null && typeof value === 'object' && ! Array.isArray(value);
}

function normalizeBlocks(blocks) {
    if (typeof blocks === 'string') {
        blocks = [blocks];
    }

    return Array.isArray(blocks)
        ? [...new Set(blocks.filter((block) => typeof block === 'string' && block.includes('/')))]
        : [];
}

function normalizeStyle(style) {
    if (! isPlainObject(style) || typeof style.name !== 'string' || style.name.trim() === '') {
        return null;
    }

    return {
        name: style.name,
        label: typeof style.label === 'string' && style.label.trim() !== '' ? style.label : style.name,
        isDefault: Boolean(style.isDefault),
        source: style.source || 'statamic',
    };
}

export function normalizeBlockStyles(payload = []) {
    return (Array.isArray(payload) ? payload : [])
        .map((item) => ({
            blocks: normalizeBlocks(item?.blocks),
            style: normalizeStyle(item?.style),
        }))
        .filter((item) => item.blocks.length && item.style);
}

export function registerStatamicBlockStyles(payload = []) {
    const styles = normalizeBlockStyles(payload);

    styles.forEach(({ blocks, style }) => {
        registerBlockStyle(blocks.length === 1 ? blocks[0] : blocks, style);
    });

    return styles;
}
