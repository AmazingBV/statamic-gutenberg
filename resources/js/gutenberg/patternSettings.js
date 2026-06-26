function isPlainObject(value) {
    return value !== null && typeof value === 'object' && ! Array.isArray(value);
}

function categoryName(category) {
    return category?.name || category?.slug || '';
}

function uniqueValues(values) {
    return [...new Set(values.map((value) => String(value || '').trim()).filter(Boolean))];
}

export function reusableBlocksForInserter(reusableBlocks, userPatternCategories) {
    const categoriesById = new Map(
        (Array.isArray(userPatternCategories) ? userPatternCategories : [])
            .filter((category) => category?.id !== undefined && categoryName(category))
            .map((category) => [String(category.id), categoryName(category)]),
    );

    return (Array.isArray(reusableBlocks) ? reusableBlocks : []).map((block) => {
        if (! isPlainObject(block)) {
            return block;
        }

        const explicitCategories = Array.isArray(block.categories) ? block.categories : [];
        const categoryIds = Array.isArray(block.wp_pattern_category) ? block.wp_pattern_category : [];
        const categorySlugs = uniqueValues([
            ...explicitCategories,
            ...categoryIds.map((id) => categoriesById.get(String(id))),
        ]);

        if (! categorySlugs.length) {
            return block;
        }

        return {
            ...block,
            wp_pattern_category: categorySlugs,
        };
    });
}

function blockContent(record) {
    if (! isPlainObject(record)) {
        return '';
    }

    if (typeof record.content === 'string') {
        return record.content;
    }

    if (isPlainObject(record.content) && typeof record.content.raw === 'string') {
        return record.content.raw;
    }

    return '';
}

function serializedBlocks(content) {
    const blocks = [];
    const pattern = /<!--\s*wp:([a-z0-9_/-]+)(?:\s+(\{.*?\}))?\s*\/?\s*-->/gis;
    let match = pattern.exec(content);

    while (match) {
        blocks.push({
            name: match[1].includes('/') ? match[1] : `core/${match[1]}`,
            attributes: decodeAttributes(match[2]),
        });
        match = pattern.exec(content);
    }

    return blocks;
}

function decodeAttributes(json) {
    if (! json) {
        return {};
    }

    try {
        const attributes = JSON.parse(json);

        return isPlainObject(attributes) ? attributes : {};
    } catch (error) {
        return {};
    }
}

function reusableBlocksById(payload) {
    return [
        ...(Array.isArray(payload.reusableBlocks) ? payload.reusableBlocks : []),
        ...(Array.isArray(payload.restReusableBlocks) ? payload.restReusableBlocks : []),
    ].reduce((records, record) => {
        if (record?.id !== undefined) {
            records.set(String(record.id), record);
        }

        return records;
    }, new Map());
}

function contentAllowed(content, allowedBlocks, reusableById, visitedRefs = new Set()) {
    if (typeof content !== 'string' || content.trim() === '') {
        return true;
    }

    const blocks = serializedBlocks(content);
    const names = blocks.map((block) => block.name);

    for (const name of names) {
        if (! allowedBlocks.has(name)) {
            return false;
        }
    }

    for (const block of blocks.filter((candidate) => candidate.name === 'core/block' && candidate.attributes?.ref !== undefined)) {
        const ref = String(block.attributes.ref);

        if (visitedRefs.has(ref)) {
            continue;
        }

        const reusableBlock = reusableById.get(ref);

        if (! reusableBlock) {
            continue;
        }

        visitedRefs.add(ref);

        if (! contentAllowed(blockContent(reusableBlock), allowedBlocks, reusableById, visitedRefs)) {
            return false;
        }
    }

    return true;
}

function categoriesForRecord(record) {
    return [
        ...(Array.isArray(record?.categories) ? record.categories : []),
        ...(Array.isArray(record?.wp_pattern_category) ? record.wp_pattern_category : []),
    ].map((value) => String(value || '').trim()).filter(Boolean);
}

function usedCategoryKeys(payload) {
    return [
        ...(Array.isArray(payload.blockPatterns) ? payload.blockPatterns : []),
        ...(Array.isArray(payload.restBlockPatterns) ? payload.restBlockPatterns : []),
        ...(Array.isArray(payload.reusableBlocks) ? payload.reusableBlocks : []),
        ...(Array.isArray(payload.restReusableBlocks) ? payload.restReusableBlocks : []),
    ].flatMap(categoriesForRecord);
}

function pruneCategories(categories, usedKeys) {
    if (! Array.isArray(categories) || ! usedKeys.size) {
        return [];
    }

    return categories.filter((category) => [
        category?.id,
        category?.name,
        category?.slug,
    ].some((key) => usedKeys.has(String(key || '').trim())));
}

export function filterPatternPayload(patterns, allowedBlockTypes = []) {
    const payload = isPlainObject(patterns) ? patterns : {};
    const allowedBlocks = Array.isArray(allowedBlockTypes)
        ? new Set(allowedBlockTypes.filter(Boolean))
        : new Set();

    if (! allowedBlocks.size) {
        return payload;
    }

    const reusableById = reusableBlocksById(payload);
    const filterRecord = (record) => contentAllowed(blockContent(record), allowedBlocks, reusableById);
    const filteredPayload = {
        ...payload,
        reusableBlocks: (Array.isArray(payload.reusableBlocks) ? payload.reusableBlocks : []).filter(filterRecord),
        restReusableBlocks: (Array.isArray(payload.restReusableBlocks) ? payload.restReusableBlocks : []).filter(filterRecord),
        blockPatterns: (Array.isArray(payload.blockPatterns) ? payload.blockPatterns : []).filter(filterRecord),
        restBlockPatterns: (Array.isArray(payload.restBlockPatterns) ? payload.restBlockPatterns : []).filter(filterRecord),
    };
    const usedKeys = new Set(usedCategoryKeys(filteredPayload));

    return {
        ...filteredPayload,
        userPatternCategories: pruneCategories(payload.userPatternCategories, usedKeys),
        blockPatternCategories: pruneCategories(payload.blockPatternCategories, usedKeys),
        restBlockPatternCategories: pruneCategories(payload.restBlockPatternCategories, usedKeys),
    };
}

export function applyPatternSettings(baseSettings, patterns, allowedBlockTypes = baseSettings?.allowedBlockTypes) {
    const payload = filterPatternPayload(patterns, allowedBlockTypes);
    const userPatternCategories = Array.isArray(payload.userPatternCategories) ? payload.userPatternCategories : [];

    return {
        ...baseSettings,
        __experimentalBlockPatterns: Array.isArray(payload.blockPatterns) ? payload.blockPatterns : [],
        __experimentalBlockPatternCategories: Array.isArray(payload.blockPatternCategories) ? payload.blockPatternCategories : [],
        __experimentalReusableBlocks: reusableBlocksForInserter(payload.reusableBlocks, userPatternCategories),
        __experimentalUserPatternCategories: userPatternCategories,
    };
}
