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

export function applyPatternSettings(baseSettings, patterns) {
    const payload = isPlainObject(patterns) ? patterns : {};
    const userPatternCategories = Array.isArray(payload.userPatternCategories) ? payload.userPatternCategories : [];

    return {
        ...baseSettings,
        __experimentalBlockPatterns: Array.isArray(payload.blockPatterns) ? payload.blockPatterns : [],
        __experimentalBlockPatternCategories: Array.isArray(payload.blockPatternCategories) ? payload.blockPatternCategories : [],
        __experimentalReusableBlocks: reusableBlocksForInserter(payload.reusableBlocks, userPatternCategories),
        __experimentalUserPatternCategories: userPatternCategories,
    };
}
