# Statamic Block Editor

Statamic addon for editing and rendering WordPress block editor content in a Laravel + Statamic project.

## Internal Developer Install

Clone this repository into the Statamic project's addon namespace directory:

```bash
cd /path/to/laravel-statamic-project
mkdir -p addons/amazingbv
git clone <repo-url> addons/amazingbv/statamic-gutenberg
```

Install the addon's PHP and JavaScript dependencies:

```bash
cd addons/amazingbv/statamic-gutenberg
composer install
npm install
npm run build
```

Register the local addon in the main Laravel + Statamic project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "addons/amazingbv/statamic-gutenberg"
        }
    ],
    "require": {
        "amazingbv/statamic-gutenberg": "*"
    }
}
```

Then install and publish the addon assets from the project root:

```bash
cd /path/to/laravel-statamic-project
composer update amazingbv/statamic-gutenberg
php artisan vendor:publish --tag=statamic-gutenberg --force
php artisan optimize:clear
```

The addon should now be available in Statamic as the `gutenberg` fieldtype.

Optional internal pattern management can be installed with:

```bash
php artisan vendor:publish --tag=statamic-gutenberg-patterns
```

This publishes a `gutenberg_patterns` collection, a `gutenberg_pattern_categories` taxonomy, and matching blueprints. Pattern entries use a block editor field for their content. Published entries appear in the editor's standard Patterns tab; entries marked `synced` are inserted as `core/block` references, while `unsynced` entries are inserted as editable copied blocks.

Optional icon block source in the Statamic project:

```php
// resources/statamic-gutenberg/icons.php
return [
    'check' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>',
    'alert' => [
        'label' => 'Alert',
        'svg' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 2 22h20z"/></svg>',
    ],
];
```

Optional block editor theme settings in the Statamic project:

```bash
mkdir -p resources/vendor/statamic-gutenberg
$EDITOR resources/vendor/statamic-gutenberg/theme.json
```

The add-on reads `resources/vendor/statamic-gutenberg/theme.json` when it exists. Settings and styles from that file are applied to both the block editor and the frontend output generated through `{{ gutenberg:styles }}`. If the file is missing, the add-on uses its built-in defaults and adds no extra theme CSS.

Theme files referenced from `theme.json` can live next to that file. For example:

```text
resources/vendor/statamic-gutenberg/theme.json
resources/vendor/statamic-gutenberg/assets/fonts/proxima-nova/proxima-nova-400-normal.woff2
```

Reference those files with WordPress-style relative URLs:

```json
{
    "settings": {
        "typography": {
            "fontFamilies": [
                {
                    "name": "Proxima Nova",
                    "slug": "proxima-nova",
                    "fontFamily": "\"Proxima Nova\"",
                    "fontFace": [
                        {
                            "fontFamily": "\"Proxima Nova\"",
                            "fontStyle": "normal",
                            "fontWeight": "400",
                            "src": ["file:./assets/fonts/proxima-nova/proxima-nova-400-normal.woff2"]
                        }
                    ]
                }
            ]
        }
    }
}
```

The add-on serves `file:./...` assets from `resources/vendor/statamic-gutenberg` through `/vendor/statamic-gutenberg/theme/...`.

## Custom Blocks

Custom blocks live in the Statamic project, not in the add-on:

```text
resources/vendor/statamic-gutenberg/blocks/custom-slider/block.json
resources/vendor/statamic-gutenberg/blocks/custom-slider/block-src.js
resources/vendor/statamic-gutenberg/blocks/custom-slider/block.js
resources/vendor/statamic-gutenberg/blocks/custom-slider/block.php
resources/vendor/statamic-gutenberg/blocks/custom-slider/block.css
resources/vendor/statamic-gutenberg/blocks/custom-slider/view.js
```

Each block gets its own subdirectory with a WordPress-compatible `block.json`. The add-on discovers all direct child directories with a `block.json` and registers those blocks in the editor. Relative `file:./...` assets are served from `/vendor/statamic-gutenberg/blocks/...`.

Supported `block.json` fields include `name`, `title`, `category`, `icon`, `description`, `attributes`, `supports`, `parent`, `ancestor`, `editorScript`, `script`, `viewScript`, `viewScriptModule`, `editorStyle`, `style`, `viewStyle`, and `render`.

Conventions:

- `block.js` is loaded in the editor when no explicit `editorScript` is set.
- `block.css` is loaded in both the editor and frontend when no explicit style field is set.
- `view.js` is loaded on the frontend when no explicit `viewScript` is set.
- `block.php` is used for frontend rendering when no explicit `render` field is set.
- `block-src.js` is used as an editor fallback when there is no explicit script field and no `block.js`.

`block.php` receives WordPress-like variables:

```php
<?php

return '<section'.get_block_wrapper_attributes(['class' => 'custom-slider']).'>'.$content.'</section>';
```

Available variables are `$attributes`, `$content`, `$block`, `$metadata`, `$renderer`, and `$render_blocks`. Container blocks can use `$render_blocks($childBlock->innerBlocks())` to render nested block content.

An internal custom tabs example can be placed at:

```text
resources/vendor/statamic-gutenberg/blocks/custom-tabs/
resources/vendor/statamic-gutenberg/blocks/custom-tab-item/
```

The parent `amazing/tabs` block uses `block.php` for frontend markup and `view.js` for tab switching. The child `amazing/tab-item` block stores the tab label and nested content.

Add the frontend assets to the site layout:

```antlers
<head>
    {{ gutenberg:styles }}
</head>
<body>
    <main class="sgb-content">
        {{ content }}
    </main>
    {{ gutenberg:scripts }}
</body>
```

`sgb-content` provides WordPress block layout widths, grid/flex layout helpers, and standard block spacing. The frontend assets include WordPress core block styles plus small Statamic-compatible JS for lightbox, accordion, tabs, and fit-text behavior.
