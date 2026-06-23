<?php

return [
    'allowed_blocks' => [
        'core/accordion',
        'core/accordion-heading',
        'core/accordion-item',
        'core/accordion-panel',
        'core/audio',
        'core/block',
        'core/button',
        'core/buttons',
        'core/code',
        'core/column',
        'core/columns',
        'core/cover',
        'core/details',
        'core/embed',
        'core/file',
        'core/gallery',
        'core/group',
        'core/heading',
        'core/icon',
        'core/image',
        'core/list',
        'core/list-item',
        'core/math',
        'core/media-text',
        'core/more',
        'core/nextpage',
        'core/paragraph',
        'core/preformatted',
        'core/pullquote',
        'core/quote',
        'core/separator',
        'core/spacer',
        'core/table',
        'core/verse',
        'core/video',
        'statamic/hero',
        'statamic/cta',
    ],

    'assets_container' => 'assets',

    'icons_path' => resource_path('statamic-gutenberg/icons.php'),

    'theme_json_path' => resource_path('vendor/statamic-gutenberg/theme.json'),

    'patterns' => [
        'collection' => 'gutenberg_patterns',
        'taxonomy' => 'gutenberg_pattern_categories',
        'content_field' => 'content',
        'sync_status_field' => 'sync_status',
        'categories_field' => 'gutenberg_pattern_categories',
        'description_field' => 'description',
        'keywords_field' => 'keywords',
        'viewport_width_field' => 'viewport_width',
        'block_types_field' => 'block_types',
        'post_types_field' => 'post_types',
        'template_types_field' => 'template_types',
        'inserter_field' => 'inserter',
    ],

    'icons' => [],

    'render_mode' => 'blade',

    'allow_unknown_blocks' => false,

    'sanitize_html' => true,

    'blocks' => [
        'statamic/hero' => [
            'view' => 'statamic-gutenberg::blocks.hero',
        ],
        'statamic/cta' => [
            'view' => 'statamic-gutenberg::blocks.cta',
        ],
    ],
];
