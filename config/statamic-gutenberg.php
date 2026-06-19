<?php

return [
    'allowed_blocks' => [
        'core/paragraph',
        'core/heading',
        'core/list',
        'core/quote',
        'core/image',
        'core/buttons',
        'core/button',
        'core/columns',
        'core/column',
        'core/group',
        'statamic/hero',
        'statamic/cta',
    ],

    'assets_container' => 'assets',

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
