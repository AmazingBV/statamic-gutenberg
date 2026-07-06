<?php

namespace Amazingbv\StatamicGutenberg\Http\Controllers;

use Amazingbv\StatamicGutenberg\CustomBlocks\CustomBlockRepository;

class CustomBlockAssetController
{
    public function __invoke(CustomBlockRepository $blocks, string $path)
    {
        $file = $blocks->publicAssetFile($path);

        abort_unless($file, 404);
        abort_unless(in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $this->allowedExtensions(), true), 404);

        return response()->file($file, [
            'Cache-Control' => 'public, max-age=31536000',
            'Content-Type' => $this->contentType($file),
        ]);
    }

    private function allowedExtensions(): array
    {
        return [
            'css',
            'js',
            'mjs',
            'png',
            'jpg',
            'jpeg',
            'webp',
            'gif',
            'svg',
            'woff',
            'woff2',
            'ttf',
            'otf',
            'eot',
        ];
    }

    private function contentType(string $file): string
    {
        return match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
            'css' => 'text/css; charset=utf-8',
            'js', 'mjs' => 'application/javascript; charset=utf-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'otf' => 'font/otf',
            'eot' => 'application/vnd.ms-fontobject',
            default => 'application/octet-stream',
        };
    }
}
