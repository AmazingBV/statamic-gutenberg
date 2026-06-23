@php
    use Illuminate\Support\Facades\Vite;
    use Statamic\Facades\Addon;

    $addon = Addon::get('amazingbv/statamic-gutenberg');
    $addonDirectory = $addon?->directory() ?? base_path('addons/amazingbv/statamic-gutenberg').'/';
    $pageTitle = $title ?: 'Block Editor';
@endphp

<!doctype html>
<html lang="{{ str_replace('_', '-', Statamic::cpLocale()) }}" dir="{{ Statamic::cpDirection() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $pageTitle }} ‹ Statamic</title>
        {{
            Vite::useHotFile($addonDirectory.'resources/dist/hot')
                ->useBuildDirectory('vendor/statamic-gutenberg/build')
                ->withEntryPoints([
                    'resources/js/editor-window.js',
                    'resources/css/addon.css',
                ])
        }}
    </head>
    <body class="sgb-standalone-body">
        <div
            id="sgb-window-root"
            data-channel="{{ $channel }}"
            data-title="{{ $pageTitle }}"
        ></div>
    </body>
</html>
