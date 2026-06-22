# Statamic Gutenberg

Statamic addon for editing and rendering Gutenberg block content in a Laravel + Statamic project.

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

Optional Gutenberg theme settings in the Statamic project:

```bash
mkdir -p resources/vendor/statamic-gutenberg
$EDITOR resources/vendor/statamic-gutenberg/theme.json
```

The add-on reads `resources/vendor/statamic-gutenberg/theme.json` when it exists. Settings and styles from that file are applied to both the Gutenberg editor and the frontend output generated through `{{ gutenberg:styles }}`. If the file is missing, the add-on uses its built-in defaults and adds no extra theme CSS.

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
