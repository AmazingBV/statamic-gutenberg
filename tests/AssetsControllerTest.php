<?php

namespace Amazingbv\StatamicGutenberg\Tests;

use Amazingbv\StatamicGutenberg\Http\Controllers\CP\AssetsController;
use Illuminate\Http\Request;
use ReflectionMethod;

class AssetsControllerTest extends TestCase
{
    public function test_asset_filters_normalize_exact_mime_types_and_extensions(): void
    {
        $controller = $this->assetsController();
        $request = Request::create('/cp/assets', 'GET', [
            'mime_types' => ['Text/VTT', 'video/*', 'bad<script>'],
            'extensions' => ['.vtt', 'PDF', '../bad'],
        ]);

        $filters = $this->invokePrivate($controller, 'assetFilters', [$request]);

        $this->assertSame(['text/vtt', 'video/*'], $filters['mime_types']);
        $this->assertSame(['vtt', 'pdf'], $filters['extensions']);
    }

    public function test_file_assets_match_exact_mime_type_filters(): void
    {
        $controller = $this->assetsController();
        $asset = new FakeAsset('text/vtt', 'vtt');

        $this->assertTrue($this->invokePrivate($controller, 'assetMatchesType', [
            $asset,
            'file',
            ['mime_types' => ['text/vtt'], 'extensions' => []],
        ]));

        $this->assertFalse($this->invokePrivate($controller, 'assetMatchesType', [
            $asset,
            'file',
            ['mime_types' => ['application/pdf'], 'extensions' => []],
        ]));
    }

    public function test_file_assets_match_exact_extension_filters(): void
    {
        $controller = $this->assetsController();
        $asset = new FakeAsset('text/plain', 'vtt');

        $this->assertTrue($this->invokePrivate($controller, 'assetMatchesType', [
            $asset,
            'file',
            ['mime_types' => [], 'extensions' => ['vtt']],
        ]));

        $this->assertFalse($this->invokePrivate($controller, 'assetMatchesType', [
            $asset,
            'file',
            ['mime_types' => [], 'extensions' => ['pdf']],
        ]));
    }

    public function test_exact_filters_still_respect_the_broad_asset_type(): void
    {
        $controller = $this->assetsController();
        $asset = new FakeAsset('text/vtt', 'vtt');

        $this->assertFalse($this->invokePrivate($controller, 'assetMatchesType', [
            $asset,
            'video',
            ['mime_types' => ['text/vtt'], 'extensions' => ['vtt']],
        ]));
    }

    public function test_wildcard_mime_type_filters_match_matching_assets(): void
    {
        $controller = $this->assetsController();
        $asset = new FakeAsset('video/mp4', 'mp4', isVideo: true);

        $this->assertTrue($this->invokePrivate($controller, 'assetMatchesType', [
            $asset,
            'video',
            ['mime_types' => ['video/*'], 'extensions' => []],
        ]));
    }

    private function invokePrivate(object $object, string $method, array $arguments): mixed
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }

    private function assetsController(): AssetsController
    {
        return new AssetsController(Request::create('/cp'));
    }
}

class FakeAsset
{
    public function __construct(
        private string $mimeType,
        private string $extension,
        private bool $isImage = false,
        private bool $isSvg = false,
        private bool $isAudio = false,
        private bool $isVideo = false,
    ) {
    }

    public function mimeType(): string
    {
        return $this->mimeType;
    }

    public function extension(): string
    {
        return $this->extension;
    }

    public function isImage(): bool
    {
        return $this->isImage;
    }

    public function isSvg(): bool
    {
        return $this->isSvg;
    }

    public function isAudio(): bool
    {
        return $this->isAudio;
    }

    public function isVideo(): bool
    {
        return $this->isVideo;
    }
}
