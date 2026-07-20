# Block Editor for Statamic

A Statamic addon for editing and rendering WordPress-compatible block content
inside a Laravel + Statamic installation.

The addon adds a `gutenberg` fieldtype to the Statamic Control Panel. Editors
open a full-size Block Editor overlay from an entry, edit Gutenberg blocks, use
Statamic Assets for media, and save the result as normal Gutenberg serialized
HTML:

```html
<!-- wp:paragraph -->
<p>Example content</p>
<!-- /wp:paragraph -->
```

On the frontend the addon parses that saved HTML and renders supported blocks
through Blade mappings, sanitized static block HTML, synced patterns, and
project-local custom blocks.

## What It Does

- Adds the `gutenberg` fieldtype to Statamic.
- Stores content in WordPress-compatible serialized block HTML.
- Opens the editor as a full-size overlay over Statamic, while keeping the
  Statamic top bar and sidebar visible.
- Provides Statamic Asset browsing and uploading for image, cover, audio, file,
  video, gallery, and media/text blocks.
- Supports the standard Gutenberg inserter, block toolbar, inspector, patterns,
  reusable/synced patterns, alignment controls, text colors, theme palettes,
  font sizes, spacing, and layout widths where supported by the bundled blocks.
- Renders frontend block output through `{{ content }}` augmentation or through
  the `Gutenberg` facade.
- Provides frontend tags for the required block styles and scripts.
- Loads optional project-local `theme.json` settings and styles.
- Loads optional project-local icon definitions for the Icon block.
- Loads optional project-local custom blocks from WordPress-compatible
  `block.json` folders.
- Provides optional Statamic-managed Patterns through a collection and taxonomy.

The addon is not a full WordPress runtime. It uses WordPress block editor
packages in the Control Panel, but media, patterns, rendering, and custom block
assets are handled by Laravel and Statamic.

## Content Field, Not An Entry Editor

Block Editor for Statamic is a body-content fieldtype. It does not replace the
Statamic entry editor or move entry metadata into the Block Editor. Keep the
title, slug, publication status, date, taxonomies, SEO fields, revisions, and
separate cover fields in Statamic.

A practical blueprint keeps the title and body content in the **Main** tab and
places supporting metadata in Statamic's **Sidebar** or dedicated SEO tabs:

```yaml
tabs:
  main:
    display: Main
    sections:
      -
        fields:
          -
            handle: title
            field:
              type: text
              required: true
          -
            handle: content
            field:
              type: gutenberg
              display: Body content
  sidebar:
    display: Sidebar
    sections:
      -
        fields:
          -
            handle: topics
            field:
              type: terms
              taxonomies:
                - topics
          -
            handle: cover_image
            field:
              type: assets
              container: assets
              max_files: 1
```

Statamic continues to provide slug, date, status, revisions, permissions, and
publishing controls around that blueprint. Render the entry title in the
Statamic template, or add a Heading block when the title should be part of the
body content. The addon does not automatically add an entry title above the
Block Editor canvas.

## Requirements

- Laravel + Statamic 6 project.
- PHP and Composer compatible with the host Statamic project.
- A Statamic asset container, by default `assets`.

## Installation In A Statamic Project

Install the addon from the root of your Laravel + Statamic project:

```bash
cd /path/to/laravel-statamic-project
composer require amazingbv/statamic-gutenberg
```

Publish the addon config and Control Panel assets, then clear the application
cache:

```bash
php artisan vendor:publish --tag=statamic-gutenberg --force
php artisan optimize:clear
```

The addon should now appear in Statamic as the `gutenberg` fieldtype.
The distributed package already contains the built editor and frontend assets,
so Node.js is not required to install or use the addon.

## Updating The Addon In A Project

Update the package and refresh the published assets from the Statamic project
root:

```bash
cd /path/to/laravel-statamic-project
composer update amazingbv/statamic-gutenberg
php artisan vendor:publish --tag=statamic-gutenberg --force
php artisan optimize:clear
```

## Basic Field Usage

Add a `gutenberg` field to a collection blueprint:

```yaml
tabs:
  main:
    display: Main
    sections:
      -
        fields:
          -
            handle: title
            field:
              type: text
              required: true
          -
            handle: content
            field:
              type: gutenberg
              display: Content
              assets_container: assets
              render_mode: blade
              allow_unknown_blocks: false
              sanitize_html: true
```

Per field you can override:

- `allowed_blocks`: block names that may be inserted, for example
  `core/paragraph`, `core/heading`, `core/image`, or a project custom block.
- `assets_container`: the Statamic Asset container used by the media picker and
  upload flow.
- `render_mode`: `blade` for parsed block rendering, or `raw` for sanitized
  saved HTML.
- `allow_unknown_blocks`: whether unsupported blocks may render their saved
  HTML.
- `sanitize_html`: whether saved/static HTML is cleaned before output.

If a field omits these options, the values from
`config/statamic-gutenberg.php` are used.

## Frontend Usage

In most Statamic templates, output the augmented field normally:

```antlers
<main class="sgb-content">
    {{ content }}
</main>
```

Add the frontend block styles and scripts to the layout:

```antlers
<!doctype html>
<html lang="{{ site:short_locale }}">
<head>
    {{ gutenberg:styles }}
</head>
<body>
    <main class="sgb-content">
        {{ template_content }}
    </main>

    {{ gutenberg:scripts }}
</body>
</html>
```

Available tags:

- `{{ gutenberg }}` or `{{ gutenberg:assets }}` outputs both styles and scripts.
- `{{ gutenberg:styles }}` outputs frontend CSS, theme.json CSS, and custom
  block frontend styles.
- `{{ gutenberg:scripts }}` outputs frontend JS and custom block frontend
  scripts.

Use `.sgb-content` around rendered content. It provides the expected WordPress
layout behavior for content width, wide/full alignment, grid/flex helpers, block
spacing, and frontend interaction hooks.

## Rendering From PHP

You can render stored Gutenberg content manually with the facade:

```php
use Gutenberg;

echo Gutenberg::render($entry->get('content'));
```

Register or override a block renderer from a service provider:

```php
use Gutenberg;

Gutenberg::block('project/notice', [
    'view' => 'blocks.notice',
]);

Gutenberg::block('custom/notice', function ($block, string $inner, $renderer): string {
    return view('blocks.notice', [
        'attrs' => $block->attributes(),
        'inner' => $inner,
    ])->render();
});
```

Configured Blade views receive:

- `$block`: the parsed block object.
- `$attrs`: block attributes.
- `$inner`: already-rendered inner block HTML as an `HtmlString`.

## Configuration

Publish the config with:

```bash
php artisan vendor:publish --tag=statamic-gutenberg --force
```

The published file is:

```text
config/statamic-gutenberg.php
```

Main options:

| Option | Purpose |
| --- | --- |
| `allowed_blocks` | Global default allowlist for blocks in the editor and renderer. |
| `assets_container` | Default Statamic Asset container for media browse/upload. |
| `icons_path` | PHP file in the host project with Icon block definitions, defaulting to `resources/vendor/statamic-gutenberg/icons.php`. |
| `theme_json_path` | Optional project-local `theme.json` path. |
| `custom_blocks_path` | Folder where project-local custom blocks live. |
| `patterns` | Collection, taxonomy, and field handles used for Statamic-managed patterns. |
| `icons` | Inline icon definitions, useful for small sets or tests. |
| `render_mode` | `blade` or `raw`. |
| `allow_unknown_blocks` | Whether unsupported blocks can render saved HTML. |
| `sanitize_html` | Whether rendered static/raw HTML is sanitized. |
| `blocks` | PHP renderer mappings for built-in or custom block names. |

Custom block names discovered from `custom_blocks_path` are automatically added
to the editor allowlist. `core/block` is also kept internally available because
Gutenberg uses it for synced pattern insertion.

## Supported Default Blocks

The default allowlist is defined in `config/statamic-gutenberg.php`. It includes:

```text
core/accordion
core/audio
core/block
core/button
core/buttons
core/code
core/column
core/columns
core/cover
core/details
core/embed
core/file
core/gallery
core/group
core/heading
core/icon
core/image
core/list
core/list-item
core/math
core/media-text
core/more
core/nextpage
core/paragraph
core/preformatted
core/pullquote
core/quote
core/separator
core/spacer
core/table
core/verse
core/video
```

Some internal child blocks are also listed where Gutenberg requires them, such
as accordion item/panel blocks. You can remove blocks from the global config or
override the allowlist per field.

## Embed Providers

`core/embed` is available for URL embeds, but the add-on intentionally supports
only iframe-based video/audio providers that can be rendered safely without
loading external provider scripts:

- YouTube
- Vimeo
- Spotify
- SoundCloud

The editor and frontend both use the same iframe-based provider handling.
YouTube and Vimeo are rendered as responsive video embeds. Spotify and
SoundCloud are rendered as rich/audio embeds with explicit preview heights, so
they stay visible in Gutenberg's sandboxed editor preview as well as on the
frontend.

Other Gutenberg oEmbed variations, such as X/Twitter, Facebook, Instagram,
Reddit, Pinterest, document providers, Maps, and generic rich/social embeds are
hidden from the inserter. If an unsupported URL is pasted into `core/embed`,
the editor uses Gutenberg's normal failed-embed flow and the frontend falls back
to the sanitized saved URL/HTML instead of rendering a custom iframe.

## Block Supports

The bundled editor registers Gutenberg's native support controls for the default
allowlisted blocks. Supported controls include wide/full alignment, anchors,
custom classes, text/background/link colors, gradients, typography, spacing,
borders, dimensions, shadows, background images, and layout controls where they
make sense for that block type.

The frontend renderer reads the same saved block attributes and applies matching
classes/styles through the addon wrapper helpers. Static saved markup is also
enriched from the block comment attributes when needed, so editor and frontend
output stay aligned even when a block's saved HTML does not already contain all
wrapper classes or inline styles.

Project-local custom blocks should declare their own WordPress-compatible
`supports` in `block.json`. The addon keeps those declarations authoritative and
only adds the attributes required for Gutenberg to persist the selected support
values, such as `style`, `align`, `textColor`, `backgroundColor`, `fontSize`,
`fontFamily`, and `borderColor`.

## Media And Assets

The editor uses Statamic Assets, not WordPress media storage. The addon exposes
a small WordPress-compatible media adapter internally so Gutenberg components
that request `/wp/v2/media` can list, search, upload, read, and update Statamic
assets without requiring a real WordPress media library.

The media picker is opened from supported media blocks and shows a file browser
for Statamic asset containers the current Control Panel user may view. Search
can run across all visible containers. Uploads default to the field's configured
asset container, or to the container currently selected in the picker, and go
through Statamic authorization, container validation rules, safe filenames, and
type checks.

The detail panel writes attachment metadata changes for alt text, title, and
caption back to the Statamic asset metadata. Existing block attributes still
store a `statamicId` alongside Gutenberg's numeric attachment id, so saved
content can be reopened without losing the Statamic asset identity.

Block type filters:

- Image blocks show images and SVGs.
- Cover and Media & Text show images, SVGs, and videos.
- Audio blocks show audio.
- Video blocks show video.
- File blocks allow files.

The v1 media adapter intentionally does not implement destructive WordPress
media operations or image editing features such as crop, rotate, and replace
original. Use Statamic's normal Assets screen for those workflows.

Configure the default container:

```php
// config/statamic-gutenberg.php
'assets_container' => 'assets',
```

Override it per field if a specific collection should use another container:

```yaml
content:
  type: gutenberg
  assets_container: downloads
```

## Editor And Live Preview Workflow

From a normal entry form, the field opens as a full-size body-content editor
while Statamic's top bar and navigation stay available.

When opened inside Statamic Live Preview, the addon uses an integrated split
view. The Block Editor stays inside the resizable editor pane and the preview
iframe remains visible beside it. List View starts closed, and compact panes
show List View and Block settings as temporary drawers.

- **Apply and save** applies the block content and requests an entry save.
- **Apply and close** applies the content and closes the editor.
- **Apply** applies the content and refreshes Live Preview without saving the
  entry.
- **Close** discards unapplied changes after confirmation.

## Theme JSON

Place an optional `theme.json` in the host Statamic project:

```bash
cd /path/to/laravel-statamic-project
mkdir -p resources/vendor/statamic-gutenberg
$EDITOR resources/vendor/statamic-gutenberg/theme.json
```

Default path:

```text
resources/vendor/statamic-gutenberg/theme.json
```

If the file exists, the addon loads its settings into the Gutenberg editor and
generates scoped CSS for the editor and frontend. If the file is missing, the
addon does nothing extra.

Common things to customize in `theme.json`:

- `settings.color.palette`
- `settings.color.gradients`
- `settings.typography.fontSizes`
- `settings.typography.fontFamilies`
- `settings.spacing.spacingSizes`
- `settings.spacing.units`
- `settings.layout.contentSize`
- `settings.layout.wideSize`
- `styles`
- `styles.elements`
- `styles.blocks`
- `styles.css`

Example:

```json
{
    "version": 3,
    "settings": {
        "layout": {
            "contentSize": "720px",
            "wideSize": "1180px"
        },
        "color": {
            "palette": [
                { "name": "Brand", "slug": "brand", "color": "#003f5c" },
                { "name": "Accent", "slug": "accent", "color": "#ef5675" }
            ]
        },
        "typography": {
            "fontSizes": [
                { "name": "Small", "slug": "small", "size": "0.875rem" },
                { "name": "Large", "slug": "large", "size": "2rem" }
            ]
        }
    },
    "styles": {
        "elements": {
            "heading": {
                "typography": {
                    "fontWeight": "700"
                }
            }
        }
    }
}
```

### Fonts And Theme Files

Files referenced from `theme.json` can live next to that file, for example:

```text
resources/vendor/statamic-gutenberg/theme.json
resources/vendor/statamic-gutenberg/assets/fonts/proxima/proxima-400.woff2
```

Reference them with WordPress-style `file:./...` URLs:

```json
{
    "settings": {
        "typography": {
            "fontFamilies": [
                {
                    "name": "Proxima",
                    "slug": "proxima",
                    "fontFamily": "\"Proxima\", sans-serif",
                    "fontFace": [
                        {
                            "fontFamily": "\"Proxima\"",
                            "fontStyle": "normal",
                            "fontWeight": "400",
                            "fontDisplay": "swap",
                            "src": [
                                "file:./assets/fonts/proxima/proxima-400.woff2"
                            ]
                        }
                    ]
                }
            ]
        }
    }
}
```

Variable fonts split across Unicode subset files can use multiple `fontFace`
records with the same family, style, and weight range:

```json
{
    "version": 3,
    "settings": {
        "typography": {
            "fontFamilies": [
                {
                    "name": "Project Mono",
                    "slug": "project-mono",
                    "fontFamily": "\"Project Mono\", monospace",
                    "fontFace": [
                        {
                            "fontFamily": "\"Project Mono\"",
                            "fontStyle": "normal",
                            "fontWeight": "100 900",
                            "fontDisplay": "swap",
                            "unicodeRange": "U+0000-00FF",
                            "src": [
                                "file:./assets/fonts/project-mono-latin.woff2"
                            ]
                        },
                        {
                            "fontFamily": "\"Project Mono\"",
                            "fontStyle": "normal",
                            "fontWeight": "100 900",
                            "fontDisplay": "swap",
                            "unicodeRange": "U+0100-024F, U+1E00-1EFF",
                            "src": [
                                "file:./assets/fonts/project-mono-extended.woff2"
                            ]
                        }
                    ]
                }
            ]
        }
    }
}
```

Supported `fontFace` descriptors are `ascentOverride`, `descentOverride`,
`fontDisplay`, `fontFamily`, `fontFeatureSettings`, `fontStyle`, `fontStretch`,
`fontVariationSettings`, `fontWeight`, `lineGapOverride`, `sizeAdjust`, `src`,
and `unicodeRange`.

The addon serves those files through:

```text
/vendor/statamic-gutenberg/theme/...
```

## Block Styles

Block styles are native Gutenberg style variations. They appear in the standard
Gutenberg Styles UI for the selected block. When an editor chooses a style, the
saved block receives WordPress' normal `is-style-{name}` class. The addon renders
the matching CSS in both the editor and frontend, scoped to the Block Editor
roots.

The default project-local file path is:

```text
resources/vendor/statamic-gutenberg/block-styles.php
```

Create it in the host Statamic project:

```bash
cd /path/to/laravel-statamic-project
mkdir -p resources/vendor/statamic-gutenberg
$EDITOR resources/vendor/statamic-gutenberg/block-styles.php
```

Example file:

```php
<?php

return [
    'core/paragraph' => [
        [
            'name' => 'lead',
            'label' => 'Lead',
            'style' => [
                'typography' => [
                    'fontSize' => 'var:preset|font-size|large',
                    'fontWeight' => '700',
                ],
            ],
        ],
    ],
    [
        'blocks' => ['core/heading', 'core/paragraph'],
        'name' => 'eyebrow',
        'label' => 'Eyebrow',
        'style' => [
            'typography' => [
                'fontSize' => '0.875rem',
                'fontWeight' => '700',
                'textTransform' => 'uppercase',
            ],
        ],
    ],
];
```

You can also register styles directly in `config/statamic-gutenberg.php`:

```php
'block_styles' => [
    'core/button' => [
        [
            'name' => 'brand-button',
            'label' => 'Brand button',
            'style' => [
                'border' => [
                    'color' => 'var:preset|color|brand',
                ],
            ],
        ],
    ],
],
```

For dynamic registration from a Laravel service provider:

```php
use Gutenberg;

Gutenberg::blockStyle('core/paragraph', [
    'name' => 'lead',
    'label' => 'Lead',
    'style' => [
        'typography' => [
            'fontWeight' => '700',
        ],
    ],
]);
```

Supported keys are:

- `name`: required style slug. The saved class becomes `is-style-{name}`.
- `label`: optional editor label. If omitted, the addon generates one from
  `name`.
- `isDefault` or `is_default`: optional boolean for Gutenberg's default style.
- `style`: optional WordPress theme.json style object.

The `style` object supports the same shape used in `theme.json`, including
`color`, `typography`, `spacing`, `border`, `dimensions`, `shadow`, nested
elements, pseudo selectors, and preset references such as
`var:preset|color|brand`. Use `theme.json styles.css` or your own project CSS
when a style needs raw custom CSS that does not fit that shape.

## Icons

The Icon block reads icons from config and from a project-local PHP file.

Default file path:

```text
resources/vendor/statamic-gutenberg/icons.php
```

Example:

```php
<?php

return [
    'check' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>',

    'alert' => [
        'label' => 'Alert',
        'svg' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 2 22h20z"/></svg>',
    ],
];
```

Each icon needs a valid SVG. SVG content is sanitized before it is exposed to the
editor.

You can also define icons directly in config:

```php
'icons' => [
    'arrow-right' => [
        'label' => 'Arrow right',
        'svg' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg>',
    ],
],
```

The file-based `icons_path` is preferred for project icons because it keeps SVG
markup out of the published config. The legacy
`resources/statamic-gutenberg/icons.php` path is still read as a fallback.

## Patterns

Patterns can be managed in Statamic through an optional collection and taxonomy.
Publish the stubs:

```bash
php artisan vendor:publish --tag=statamic-gutenberg-patterns
```

This creates:

```text
content/collections/gutenberg_patterns.yaml
content/taxonomies/gutenberg_pattern_categories.yaml
resources/blueprints/collections/gutenberg_patterns/pattern.yaml
resources/blueprints/taxonomies/gutenberg_pattern_categories/category.yaml
```

The collection appears in the Control Panel as `Patterns`.

Pattern entries use Gutenberg-compatible fields:

- `content`: block editor content for the pattern.
- `sync_status`: `synced` or `unsynced`.
- `gutenberg_pattern_categories`: taxonomy terms for inserter categories.
- `description`: optional pattern description.
- `keywords`: optional search keywords.
- `viewport_width`: optional preview width.
- `block_types`: optional associated block types.
- `post_types`: optional associated post types.
- `template_types`: optional associated template types.
- `inserter`: false hides a published pattern from the inserter.

Only published patterns are loaded. Draft patterns are ignored.

Behavior:

- `unsynced` patterns are inserted as normal editable blocks.
- `synced` patterns are inserted as `core/block` references.
- Synced patterns render on the frontend by looking up the referenced pattern
  entry and rendering its content through the same block renderer.
- Circular synced pattern references render empty to avoid recursion loops.

You can change the collection, taxonomy, and field handles in the `patterns`
config array.

## Custom Blocks

Custom blocks live in the host Statamic project, not inside this addon. Default
folder:

```text
resources/vendor/statamic-gutenberg/blocks
```

Each direct child folder with a `block.json` is discovered automatically:

```text
resources/vendor/statamic-gutenberg/blocks/vertical-tabs/block.json
resources/vendor/statamic-gutenberg/blocks/vertical-tabs/index.js
resources/vendor/statamic-gutenberg/blocks/vertical-tabs/index.css
resources/vendor/statamic-gutenberg/blocks/vertical-tabs/style-index.css
resources/vendor/statamic-gutenberg/blocks/vertical-tabs/render.php
resources/vendor/statamic-gutenberg/blocks/vertical-tabs/view.js
```

Supported `block.json` fields include:

```text
$schema
apiVersion
name
title
category
icon
description
keywords
attributes
supports
parent
ancestor
allowedBlocks
editorScript
editorScriptModule
script
viewScript
viewScriptModule
editorStyle
style
viewStyle
render
```

When a custom block renders or templates inner blocks, those block names also
need to be allowed by Gutenberg. The addon reads dependencies from official
`allowedBlocks` and `template` metadata, plus an optional Statamic-specific
`statamic.requiredBlocks` list:

```json
{
    "name": "amazing/vertical-tabs",
    "allowedBlocks": ["amazing/vertical-tab"],
    "statamic": {
        "requiredBlocks": ["core/group", "core/image", "core/paragraph"]
    }
}
```

Asset values may be strings, lists, or objects with `src`, `path`, or `file`.
Local assets should use `file:./...` paths. External `https://...` and absolute
`/...` URLs are also accepted.

Example `block.json`:

```json
{
    "apiVersion": 3,
    "name": "amazing/notice",
    "title": "Notice",
    "category": "design",
    "icon": "info",
    "attributes": {
        "message": {
            "type": "string",
            "default": ""
        }
    },
    "supports": {
        "align": ["wide", "full"],
        "color": {
            "text": true,
            "background": true
        }
    },
    "editorScript": "file:./index.js",
    "editorStyle": "file:./index.css",
    "style": "file:./style-index.css",
    "viewScript": "file:./view.js",
    "render": "file:./render.php"
}
```

Conventions when no explicit `block.json` asset field is set:

- `block.js` is loaded in the editor.
- `block-src.js` is loaded in the editor if `block.js` is missing.
- `block.css` is loaded in the editor and frontend.
- `view.js` is loaded on the frontend.
- `block.php` is used for frontend rendering.

Generated WordPress block builds are supported. If a bundled editor script calls
`registerBlockType(name, { edit, save })`, the addon merges the matching
`block.json` metadata before registering the block.

### Custom Block Rendering

Use `render.php` or `block.php` for server-side frontend rendering:

```php
<?php

$class = trim('amazing-notice '.($attributes['className'] ?? ''));

?>
<section <?= get_block_wrapper_attributes(['class' => $class]) ?>>
    <?= $content ?>
</section>
```

Render files can use:

- `$attributes`: block attributes.
- `$content`: rendered inner block HTML.
- `$block`: WordPress-like block object.
- `$metadata`: sanitized `block.json` metadata.
- `$renderer`: the block renderer instance.
- `$render_blocks`: helper for rendering a custom list of inner blocks.

Available helper shims include common WordPress-like functions such as
`get_block_wrapper_attributes()`, `esc_attr()`, `esc_html()`, `esc_url()`,
`sanitize_title()`, `wp_json_encode()`, and `wp_unique_id()`.

Local custom block assets are served through:

```text
/vendor/statamic-gutenberg/blocks/...
```

The addon appends cache-busting `?ver=` values from matching `.asset.php` files
when available, or from file modification times.

## Security Notes

Default rendering is intentionally conservative:

- Unsupported blocks render empty unless `allow_unknown_blocks` is true.
- Static/raw HTML is sanitized when `sanitize_html` is true.
- SVG icons are sanitized before use.
- Custom block asset routes only serve files inside the configured custom block
  directory.
- Theme `file:./...` URLs cannot escape the configured theme directory.
- Media uploads use Statamic authorization and validation.

Only disable `sanitize_html` or enable `allow_unknown_blocks` for trusted
content and known block sources.

## Development Commands

Contributors can clone the public repository and install its PHP and JavaScript
dependencies:

```bash
git clone https://github.com/AmazingBV/statamic-gutenberg.git
cd statamic-gutenberg
composer install
npm install
```

Build production assets:

```bash
npm run build
```

Run JavaScript tests:

```bash
npm test
```

Run PHP tests:

```bash
./vendor/bin/phpunit
```

When testing inside a host Statamic project after a build:

```bash
cd /path/to/laravel-statamic-project
php artisan vendor:publish --tag=statamic-gutenberg --force
php artisan optimize:clear
```

## Troubleshooting

### The fieldtype is not visible

Check that the package is installed in the host project:

```bash
composer show amazingbv/statamic-gutenberg
php artisan optimize:clear
```

### The editor opens but styles or scripts are old

Update the addon and republish its assets:

```bash
cd /path/to/laravel-statamic-project
composer update amazingbv/statamic-gutenberg
php artisan vendor:publish --tag=statamic-gutenberg --force
php artisan optimize:clear
```

### Media picker is empty

Check:

- The configured `assets_container` exists.
- The current user can view/upload assets.
- The selected block type allows the media type you are trying to choose.
- The asset is in the current folder shown by the picker.

### Patterns do not appear

Check:

- The pattern collection stubs were published.
- The pattern entry is published.
- `inserter` is not false/off.
- The pattern has content.
- The pattern category field points to the configured taxonomy.

### theme.json affects frontend but not editor

Check:

- The file is at `resources/vendor/statamic-gutenberg/theme.json`, or the config
  points to the custom path.
- The editor page was reloaded after changing the file.
- The CSS is scoped to `.sgb-editor .sgb-canvas`; toolbar UI should not inherit
  content styles.

### A theme font does not load

Check that every `file:./...` source exists relative to `theme.json`, uses a
safe path inside the theme directory, and is readable by Laravel. For subset
fonts, every `unicodeRange` value must be a comma-separated list of valid
`U+...` values, ranges, or wildcards. Reload the Control Panel after changing
font files or `theme.json`.

### A font-size preset looks different than expected

Define the preset under `settings.typography.fontSizes` and apply it through the
native Typography control. Preset classes such as `has-small-font-size` use the
matching `--wp--preset--font-size--small` value in both editor and frontend.
Check project CSS for a more specific selector when an explicit preset is still
overridden.

### A border appears unexpectedly

The addon does not create a visible border from only a border color or radius.
A visible theme border requires an explicit border width or style. If a border
is still visible, inspect project CSS, a block style variation, or the block's
saved `style.border.width` and `style.border.style` attributes.

### A custom block shows "Custom block"

Check:

- The block folder is inside `resources/vendor/statamic-gutenberg/blocks`.
- `block.json` has a valid block name such as `vendor/block-name`.
- The editor script URL returns 200.
- The editor script calls `wp.blocks.registerBlockType`.
- After changing JS, rebuild/publish addon assets and reload the editor.

## License And Commercial Use

This addon is distributed under the GNU General Public License version 2 only
(`GPL-2.0-only`). The full license text is included in `LICENSE.md`, and the
same license is declared in `composer.json` and `package.json`.

When you buy or receive the addon from our official distribution channel, you
get access to the packaged addon, maintained releases, updates, documentation,
and support. The payment is for that official delivery and support model. It
does not remove or limit the rights you receive under the GPL.

Under `GPL-2.0-only`, you may use the addon, inspect the source code, modify it
for your own project, and redistribute your copy under the same license. If you
redistribute the addon or a modified version of it, the redistributed copy must
also remain available under `GPL-2.0-only`.

This license applies to the addon package itself. Your own Statamic project,
content, templates, assets, project-specific `theme.json`, icon definitions,
patterns, and custom blocks remain your own unless you choose to distribute
them together with the addon as part of one package.

The addon does not include a Statamic CMS license. You still need a valid
Statamic license for each Statamic project where Statamic requires one.

The addon includes and builds on WordPress Block Editor packages and Dashicons.
Those dependencies require the distributed addon package to use `GPL-2.0-only`
as its license.
