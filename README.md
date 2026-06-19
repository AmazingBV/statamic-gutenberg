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
