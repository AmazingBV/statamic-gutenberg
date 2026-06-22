<?php

namespace Amazingbv\StatamicGutenberg\Http\Controllers;

use Amazingbv\StatamicGutenberg\ThemeJson;
class ThemeAssetController
{
    public function __invoke(ThemeJson $themeJson, string $path)
    {
        $base = realpath(dirname($themeJson->path()));

        abort_unless($base, 404);

        $relative = ltrim(str_replace('\\', '/', rawurldecode($path)), '/');

        abort_if($relative === '' || str_contains($relative, '..'), 404);

        $file = realpath($base.'/'.$relative);

        abort_unless($file && str_starts_with($file, $base.DIRECTORY_SEPARATOR) && is_file($file), 404);
        abort_unless(in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $this->allowedExtensions(), true), 404);

        return response()->file($file, [
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }

    private function allowedExtensions(): array
    {
        return [
            'woff',
            'woff2',
            'ttf',
            'otf',
            'eot',
            'svg',
            'css',
            'json',
            'png',
            'jpg',
            'jpeg',
            'webp',
            'gif',
        ];
    }
}
