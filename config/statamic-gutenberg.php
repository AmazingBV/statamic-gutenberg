<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Allowed Blocks
    |--------------------------------------------------------------------------
    |
    | Global default allowlist for blocks that may be inserted in the editor and
    | rendered on the frontend. You can override this per `gutenberg` field in a
    | blueprint with an `allowed_blocks` list.
    |
    | Custom blocks discovered in `custom_blocks_path` are added automatically.
    | `core/block` is kept available internally because Gutenberg uses it for
    | synced pattern references, even if you do not show it as a normal inserter
    | block.
    |
    */
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
    ],

    /*
    |--------------------------------------------------------------------------
    | Assets Container
    |--------------------------------------------------------------------------
    |
    | Default Statamic Asset container used by the editor media browser and
    | upload flow. The fieldtype can override this per field with
    | `assets_container`.
    |
    */
    'assets_container' => 'assets',

    /*
    |--------------------------------------------------------------------------
    | Icon Source
    |--------------------------------------------------------------------------
    |
    | The Icon block reads SVG definitions from the inline `icons` config below
    | and from this optional PHP file in the host Statamic project. Keeping SVGs
    | in a project file is usually cleaner than editing this published config.
    |
    | Expected file shape:
    |
    | return [
    |     'check' => '<svg ...></svg>',
    |     'alert' => ['label' => 'Alert', 'svg' => '<svg ...></svg>'],
    | ];
    |
    */
    'icons_path' => resource_path('vendor/statamic-gutenberg/icons.php'),

    /*
    |--------------------------------------------------------------------------
    | Theme JSON
    |--------------------------------------------------------------------------
    |
    | Optional project-local WordPress-style theme.json. When the file exists,
    | the addon loads its settings into the editor and generates scoped CSS for
    | both `.sgb-editor .sgb-canvas` and `.sgb-content`.
    |
    | Use it for palettes, gradients, font sizes, font families, spacing sizes,
    | layout content/wide widths, element styles, block styles, and custom CSS.
    | Files referenced as `file:./...` are served from this directory through
    | `/vendor/statamic-gutenberg/theme/...`.
    |
    */
    'theme_json_path' => resource_path('vendor/statamic-gutenberg/theme.json'),

    /*
    |--------------------------------------------------------------------------
    | Custom Blocks
    |--------------------------------------------------------------------------
    |
    | Project-local custom blocks are discovered from direct child directories in
    | this path. Each block directory needs a WordPress-compatible `block.json`.
    |
    | Supported asset fields include `editorScript`, `editorScriptModule`,
    | `script`, `viewScript`, `viewScriptModule`, `editorStyle`, `style`,
    | `viewStyle`, and `render`. Local `file:./...` assets are served through
    | `/vendor/statamic-gutenberg/blocks/...`.
    |
    | If a custom block needs extra inner blocks while a field uses a narrow
    | `allowed_blocks` list, add them to the block's project-local `block.json`
    | with `allowedBlocks`, a `template`, or `statamic.requiredBlocks`.
    |
    */
    'custom_blocks_path' => resource_path('vendor/statamic-gutenberg/blocks'),

    /*
    |--------------------------------------------------------------------------
    | Bard Blocks
    |--------------------------------------------------------------------------
    |
    | When enabled, Statamic Bard sets are exposed as Gutenberg blocks. The
    | editor stores all set field values in block attributes, shows the controls
    | in the sidebar, and renders only a server-side preview in the canvas.
    |
    | With `sources` set to `auto`, Bard sets are discovered from project and
    | namespaced blueprints plus project fieldsets. If two sets use the same
    | handle, the generated block name is prefixed with a source slug, e.g.
    | `bard/pages-content-hero`.
    |
    | Rendering looks for an explicit view in `views` first. Keys can be the
    | full block name (`bard/hero`), `source.set` (`pages.content.hero`), or the
    | set handle (`hero`). If no explicit view is configured, the repository will
    | try the `view_prefixes` conventions. Missing render views return empty
    | output by default; set `missing_behavior` to `placeholder` while developing
    | if you want visible fallback markup.
    |
    */
    'bard_blocks' => [
        'enabled' => true,
        'sources' => 'auto',
        'unknown_field_fallback' => 'textarea',
        'preview_debounce_ms' => 300,
        'block_namespace' => 'bard',
        'blueprints_path' => resource_path('blueprints'),
        'fieldsets_path' => resource_path('fieldsets'),
        'views' => [],
        'view_prefixes' => [
            'bard',
            'sets',
            'partials.bard',
            'partials.sets',
        ],
        'missing_behavior' => 'empty',
    ],

    /*
    |--------------------------------------------------------------------------
    | Patterns
    |--------------------------------------------------------------------------
    |
    | Statamic-managed patterns are loaded from a collection and optional
    | taxonomy. Publish the default collection/taxonomy/blueprint stubs with:
    |
    | php artisan vendor:publish --tag=statamic-gutenberg-patterns
    |
    | Only published entries are loaded. `inserter: false` keeps a pattern out of
    | the inserter. `sync_status: synced` inserts a `core/block` reference;
    | `sync_status: unsynced` inserts editable copied blocks.
    |
    | Change these handles if a project already has different collection,
    | taxonomy, or field names.
    |
    */
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

    /*
    |--------------------------------------------------------------------------
    | Inline Icons
    |--------------------------------------------------------------------------
    |
    | Optional inline SVG definitions for the Icon block. Values can be raw SVG
    | strings or arrays with `label` and `svg`/`content`. These are merged with
    | icons from `icons_path`. SVG markup is sanitized before use.
    |
    */
    'icons' => [],

    /*
    |--------------------------------------------------------------------------
    | Render Mode
    |--------------------------------------------------------------------------
    |
    | `blade` parses Gutenberg serialized content and renders supported blocks
    | through Blade mappings, custom render files, synced patterns, and sanitized
    | static HTML.
    |
    | `raw` skips block rendering and outputs the saved HTML directly. Keep
    | `sanitize_html` enabled unless the content source is fully trusted.
    |
    */
    'render_mode' => 'blade',

    /*
    |--------------------------------------------------------------------------
    | Unknown Blocks
    |--------------------------------------------------------------------------
    |
    | When false, unsupported blocks render empty in `blade` mode. When true,
    | unsupported blocks may render their saved static HTML, still passing
    | through the sanitizer when `sanitize_html` is enabled.
    |
    */
    'allow_unknown_blocks' => false,

    /*
    |--------------------------------------------------------------------------
    | HTML Sanitizing
    |--------------------------------------------------------------------------
    |
    | Sanitizes freeform, static, unknown, and raw block HTML before output.
    | Leave enabled for normal content editing. Disable only for trusted content
    | where raw HTML output is intentional.
    |
    */
    'sanitize_html' => true,

    /*
    |--------------------------------------------------------------------------
    | Block Render Mappings
    |--------------------------------------------------------------------------
    |
    | Map block names to Blade views or register them at runtime with
    | Gutenberg::block(). The view receives:
    |
    | - $block: parsed block object
    | - $attrs: block attributes
    | - $inner: rendered inner blocks as an HtmlString
    |
    | Custom blocks discovered from `custom_blocks_path` do not need an entry
    | here when they provide `render` in block.json or a conventional block.php.
    |
    */
    'blocks' => [],
];
