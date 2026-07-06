<?php

namespace Amazingbv\StatamicGutenberg\Http\Controllers;

use Amazingbv\StatamicGutenberg\ThemeJson;

class ThemeAssetController
{
    public function __invoke(ThemeJson $themeJson, string $path)
    {
        $file = $themeJson->publicAssetFile($path);

        abort_unless($file, 404);
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
            'png',
            'jpg',
            'jpeg',
            'webp',
            'gif',
        ];
    }
}
