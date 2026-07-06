<?php

namespace Amazingbv\StatamicGutenberg\Tests;

use Amazingbv\StatamicGutenberg\Http\Controllers\CP\AssetsController;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use ReflectionMethod;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\User;

class AssetsControllerTest extends TestCase
{
    public function test_asset_browser_requires_container_view_permission(): void
    {
        $container = AssetContainer::make('private');
        AssetContainer::shouldReceive('find')->with('private')->andReturn($container);
        AssetContainer::shouldReceive('all')->andReturn(collect([$container]));

        $this->actingAs(User::make()->id('editor')->email('editor@example.com'));
        $this->expectException(AuthorizationException::class);

        $this->assetsController()->index(Request::create('/cp/assets', 'GET', [
            'container' => 'private',
        ]));
    }

    public function test_all_container_requests_use_view_authorized_containers(): void
    {
        $container = AssetContainer::make('assets');
        AssetContainer::shouldReceive('all')->andReturn(collect([$container]));
        Gate::shouldReceive('allows')->with('view', $container)->andReturn(true);

        $containers = $this->invokePrivate($this->assetsController(), 'containersForRequest', [
            Request::create('/cp/assets', 'GET', [
                'container' => '*',
            ]),
        ]);

        $this->assertSame([$container], $containers);
    }

    public function test_asset_payload_contains_wordpress_compatible_media_metadata(): void
    {
        $asset = new FakeAsset(
            'image/jpeg',
            'jpg',
            isImage: true,
            id: 'assets::hero.jpg',
            basename: 'hero.jpg',
            url: 'https://example.test/storage/assets/hero.jpg',
            data: [
                'alt' => 'Hero alt',
                'title' => 'Hero title',
                'caption' => 'Hero caption',
            ],
            width: 1200,
            height: 800,
            size: 123456,
        );

        $payload = $this->invokePrivate($this->assetsController(), 'assetPayload', [
            $asset,
            Request::create('https://example.test/cp/assets'),
        ]);

        $this->assertSame('assets::hero.jpg', $payload['id']);
        $this->assertIsInt($payload['wpId']);
        $this->assertSame('assets::hero.jpg', $payload['statamicId']);
        $this->assertSame('assets', $payload['container']);
        $this->assertSame('Hero alt', $payload['alt_text']);
        $this->assertSame('Hero title', $payload['title']);
        $this->assertSame('Hero caption', $payload['caption']);
        $this->assertSame('image', $payload['media_type']);
        $this->assertSame(1200, $payload['media_details']['width']);
        $this->assertSame(800, $payload['media_details']['height']);
        $this->assertSame(123456, $payload['media_details']['filesize']);
        $this->assertSame('https://example.test/storage/assets/hero.jpg', $payload['media_details']['sizes']['full']['source_url']);
    }

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

    public function test_folder_tree_payload_returns_nested_statamic_asset_folders(): void
    {
        $payload = $this->invokePrivate($this->assetsController(), 'folderTreePayload', [
            new FakeFolderContainer([
                '/' => [
                    new FakeFolder('images', 'Images'),
                    new FakeFolder('downloads', 'Downloads'),
                ],
                'images' => [
                    new FakeFolder('images/home', 'Home'),
                ],
                'images/home' => [
                    new FakeFolder('images/home/hero', 'Hero'),
                ],
            ]),
        ]);

        $this->assertSame([
            [
                'path' => 'images',
                'title' => 'Images',
                'basename' => 'images',
                'parent' => '/',
                'children' => [
                    [
                        'path' => 'images/home',
                        'title' => 'Home',
                        'basename' => 'home',
                        'parent' => 'images',
                        'children' => [
                            [
                                'path' => 'images/home/hero',
                                'title' => 'Hero',
                                'basename' => 'hero',
                                'parent' => 'images/home',
                                'children' => [],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'path' => 'downloads',
                'title' => 'Downloads',
                'basename' => 'downloads',
                'parent' => '/',
                'children' => [],
            ],
        ], $payload);
    }

    public function test_container_payloads_include_folder_tree_for_the_file_browser(): void
    {
        $container = new FakeAssetContainer('assets', 'Assets', [
            '/' => [
                new FakeFolder('images', 'Images'),
            ],
        ]);

        AssetContainer::shouldReceive('all')->andReturn(collect([$container]));
        Gate::shouldReceive('allows')->with('view', $container)->andReturn(true);

        $payload = $this->invokePrivate($this->assetsController(), 'containerPayloads', []);

        $this->assertSame([
            [
                'handle' => 'assets',
                'title' => 'Assets',
                'folder_tree' => [
                    [
                        'path' => 'images',
                        'title' => 'Images',
                        'basename' => 'images',
                        'parent' => '/',
                        'children' => [],
                    ],
                ],
            ],
        ], $payload);
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

class FakeAssetContainer extends FakeFolderContainer
{
    public function __construct(
        private string $handle,
        private string $title,
        array $foldersByParent,
    ) {
        parent::__construct($foldersByParent);
    }

    public function handle(): string
    {
        return $this->handle;
    }

    public function title(): string
    {
        return $this->title;
    }
}

class FakeFolderContainer
{
    public function __construct(private array $foldersByParent)
    {
    }

    public function assetFolders(string $parent, bool $recursive)
    {
        return collect($this->foldersByParent[$parent] ?? []);
    }
}

class FakeFolder
{
    public function __construct(
        private string $path,
        private string $title,
    ) {
    }

    public function path(): string
    {
        return $this->path;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function basename(): string
    {
        return basename($this->path);
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
        private string $id = 'assets::file.txt',
        private string $basename = 'file.txt',
        private string $url = '/storage/assets/file.txt',
        private array $data = [],
        private ?int $width = null,
        private ?int $height = null,
        private ?int $size = null,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function thumbnailUrl(string $preset): string
    {
        return $this->url.'?preset='.$preset;
    }

    public function get(string $key, mixed $fallback = null): mixed
    {
        return $this->data[$key] ?? $fallback;
    }

    public function basename(): string
    {
        return $this->basename;
    }

    public function containerHandle(): string
    {
        return explode('::', $this->id, 2)[0] ?: 'assets';
    }

    public function path(): string
    {
        return explode('::', $this->id, 2)[1] ?? $this->basename;
    }

    public function folder(): string
    {
        $folder = dirname($this->path());

        return $folder === '.' ? '/' : $folder;
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

    public function width(): ?int
    {
        return $this->width;
    }

    public function height(): ?int
    {
        return $this->height;
    }

    public function size(): ?int
    {
        return $this->size;
    }
}
