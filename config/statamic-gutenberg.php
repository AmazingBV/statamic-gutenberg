<?php

use Amazingbv\StatamicGutenberg\Blocks\CoreBlocks;

return [
    'allowed_blocks' => array_merge(CoreBlocks::names(), [
        'statamic/hero',
        'statamic/cta',
    ]),

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
