<?php

namespace Amazingbv\StatamicGutenberg\Http\Controllers\CP;

use Facades\Statamic\Fields\Validator as FieldValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Statamic\Assets\AssetUploader;
use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;
use Statamic\Http\Controllers\CP\CpController;
use Statamic\Rules\AllowedFile;
use Statamic\Rules\UploadableAssetPath;
use Throwable;

class AssetsController extends CpController
{
    public function index(Request $request): JsonResponse
    {
        $containers = $this->containersForRequest($request);
        $type = $this->normalType((string) $request->query('type', 'image'));
        $filters = $this->assetFilters($request);
        $folder = $this->normalFolder((string) $request->query('folder', '/'));
        $search = strtolower(trim((string) $request->query('q', '')));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 80)));
        $offset = ($page - 1) * $perPage;

        $assets = collect($containers)
            ->flatMap(fn ($container) => $container->assets($folder, false))
            ->filter(fn ($asset) => $this->assetMatchesType($asset, $type, $filters))
            ->filter(function ($asset) use ($search) {
                if ($search === '') {
                    return true;
                }

                return str_contains(strtolower($asset->basename()), $search)
                    || str_contains(strtolower((string) $asset->get('alt')), $search)
                    || str_contains(strtolower($asset->path()), $search)
                    || str_contains(strtolower((string) $asset->get('caption')), $search)
                    || str_contains(strtolower((string) $asset->get('title')), $search)
                    || str_contains(strtolower((string) $asset->containerHandle()), $search);
            })
            ->values();

        $total = $assets->count();
        $assets = $assets
            ->slice($offset, $perPage)
            ->map(fn ($asset) => $this->assetPayload($asset, $request))
            ->all();

        $folders = count($containers) === 1
            ? $containers[0]->assetFolders($folder, false)
                ->values()
                ->map(fn ($folder) => $this->folderPayload($folder))
                ->all()
            : [];

        return response()->json([
            'data' => $assets,
            'folders' => $folders,
            'containers' => $this->containerPayloads(),
            'folder' => $folder,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) max(1, ceil($total / $perPage)),
            ],
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $asset = $this->findRequestedAsset($request);

        abort_unless($asset, 404);

        $this->authorize('view', $asset);

        return response()->json(['data' => $this->assetPayload($asset, $request)]);
    }

    public function update(Request $request): JsonResponse
    {
        $asset = $this->findRequestedAsset($request);

        abort_unless($asset, 404);

        $this->authorize('edit', $asset);

        $validated = $request->validate([
            'alt' => ['sometimes', 'nullable', 'string'],
            'alt_text' => ['sometimes', 'nullable', 'string'],
            'title' => ['sometimes', 'nullable'],
            'caption' => ['sometimes', 'nullable'],
        ]);

        if (array_key_exists('alt_text', $validated) || array_key_exists('alt', $validated)) {
            $asset->set('alt', (string) ($validated['alt_text'] ?? $validated['alt'] ?? ''));
        }

        if (array_key_exists('title', $validated)) {
            $asset->set('title', $this->rawString($validated['title']));
        }

        if (array_key_exists('caption', $validated)) {
            $asset->set('caption', $this->rawString($validated['caption']));
        }

        $asset->save();

        return response()->json(['data' => $this->assetPayload($asset, $request)]);
    }

    public function upload(Request $request): JsonResponse
    {
        $handle = $request->input('container', $request->query('container', config('statamic-gutenberg.assets_container', 'assets')));
        $container = AssetContainer::find($handle);

        abort_unless($container, 404);

        $this->authorize('store', [AssetContract::class, $container]);

        $type = $this->normalType((string) $request->input('type', $request->query('type', 'image')));
        $filters = $this->assetFilters($request);
        $folder = $this->normalFolder((string) $request->input('folder', $request->query('folder', '/')));

        $validationRules = collect($container->validationRules())
            ->map(fn ($rule) => FieldValidator::parse($rule))
            ->all();

        $request->validate([
            'file' => array_merge(['required', 'file', new AllowedFile], $validationRules),
        ]);

        $file = $request->file('file');
        $path = $this->uniquePath(
            $container,
            $folder,
            $file->getClientOriginalName()
        );

        Validator::make(['path' => $path], ['path' => new UploadableAssetPath($container)])->validate();

        $asset = $container->makeAsset($path)->upload($file);

        if (! $this->assetMatchesType($asset, $type, $filters)) {
            $asset->delete();

            throw ValidationException::withMessages([
                'file' => __('The uploaded file is not allowed for this block.'),
            ]);
        }

        return response()->json(['data' => $this->assetPayload($asset, $request)], 201);
    }

    private function containersForRequest(Request $request): array
    {
        $handle = (string) $request->query('container', config('statamic-gutenberg.assets_container', 'assets'));

        if (in_array($handle, ['*', 'all'], true)) {
            return $this->visibleContainers();
        }

        $container = AssetContainer::find($handle);

        if (! $container) {
            return [];
        }

        $this->authorize('view', $container);

        return [$container];
    }

    private function visibleContainers(): array
    {
        return AssetContainer::all()
            ->filter(fn ($container) => Gate::allows('view', $container))
            ->values()
            ->all();
    }

    private function containerPayloads(): array
    {
        return collect($this->visibleContainers())
            ->map(fn ($container) => [
                'handle' => $container->handle(),
                'title' => $container->title() ?: $container->handle(),
            ])
            ->values()
            ->all();
    }

    private function findRequestedAsset(Request $request)
    {
        $id = (string) $request->input('id', $request->query('id', ''));

        if ($id === '') {
            return null;
        }

        return Asset::find($id);
    }

    private function uniquePath($container, string $folder, string $originalName): string
    {
        $folder = trim(AssetUploader::getSafePath($folder), '/');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $filename = AssetUploader::getSafeFilename(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'asset';
        $candidate = $extension ? "{$filename}.{$extension}" : $filename;
        $path = ltrim(($folder ? $folder.'/' : '').$candidate, '/');
        $index = 2;

        while ($container->asset($path)) {
            $candidate = $extension ? "{$filename}-{$index}.{$extension}" : "{$filename}-{$index}";
            $path = ltrim(($folder ? $folder.'/' : '').$candidate, '/');
            $index++;
        }

        return $path;
    }

    private function normalFolder(string $folder): string
    {
        $folder = trim(AssetUploader::getSafePath($folder), '/');

        return $folder === '' ? '/' : $folder;
    }

    private function normalType(string $type): string
    {
        return match ($type) {
            'audio', 'file', 'image', 'video', 'visual' => $type,
            default => 'file',
        };
    }

    private function assetFilters(Request $request): array
    {
        return [
            'mime_types' => $this->normalMimeTypes($request->input('mime_types', $request->query('mime_types', []))),
            'extensions' => $this->normalExtensions($request->input('extensions', $request->query('extensions', []))),
        ];
    }

    private function normalMimeTypes(mixed $value): array
    {
        return collect(is_array($value) ? $value : [$value])
            ->map(fn ($mimeType) => strtolower(trim((string) $mimeType)))
            ->filter(fn ($mimeType) => (bool) preg_match('/^[a-z0-9.+-]+\/[a-z0-9.+*-]+$/i', $mimeType))
            ->values()
            ->all();
    }

    private function normalExtensions(mixed $value): array
    {
        return collect(is_array($value) ? $value : [$value])
            ->map(fn ($extension) => strtolower(ltrim(trim((string) $extension), '.')))
            ->filter(fn ($extension) => (bool) preg_match('/^[a-z0-9]+$/i', $extension))
            ->values()
            ->all();
    }

    private function assetMatchesType($asset, string $type, array $filters = []): bool
    {
        if (! $this->assetMatchesBroadType($asset, $type)) {
            return false;
        }

        $mimeTypes = $filters['mime_types'] ?? [];
        $extensions = $filters['extensions'] ?? [];

        if ($mimeTypes === [] && $extensions === []) {
            return true;
        }

        $mimeType = strtolower((string) $asset->mimeType());
        $extension = strtolower((string) $asset->extension());

        $mimeMatches = collect($mimeTypes)->contains(function ($allowedMimeType) use ($mimeType) {
            if (str_ends_with($allowedMimeType, '/*')) {
                return str_starts_with($mimeType, substr($allowedMimeType, 0, -1));
            }

            return $mimeType === $allowedMimeType;
        });

        $extensionMatches = in_array($extension, $extensions, true);

        return $mimeMatches || $extensionMatches;
    }

    private function assetMatchesBroadType($asset, string $type): bool
    {
        return match ($type) {
            'audio' => $asset->isAudio(),
            'image' => $asset->isImage() || $asset->isSvg(),
            'video' => $asset->isVideo(),
            'visual' => $asset->isImage() || $asset->isSvg() || $asset->isVideo(),
            default => true,
        };
    }

    private function folderPayload($folder): array
    {
        $path = trim((string) $folder->path(), '/');
        $parent = dirname($path);

        if ($parent === '.' || $parent === '') {
            $parent = '/';
        }

        return [
            'path' => $path === '' ? '/' : $path,
            'title' => $folder->title(),
            'basename' => $folder->basename(),
            'parent' => $parent,
        ];
    }

    private function assetPayload($asset, Request $request): array
    {
        $url = $this->sameSchemeUrl($asset->url(), $request);
        $mediaType = $this->mediaType($asset);
        $thumbnail = null;
        $width = null;
        $height = null;
        $size = null;

        try {
            $thumbnail = $asset->isImage() || $asset->isSvg()
                ? $this->sameSchemeUrl($asset->thumbnailUrl('small'), $request)
                : null;
        } catch (Throwable) {
            $thumbnail = null;
        }

        try {
            $width = method_exists($asset, 'width') ? $asset->width() : null;
            $height = method_exists($asset, 'height') ? $asset->height() : null;
        } catch (Throwable) {
            $width = $height = null;
        }

        try {
            $size = method_exists($asset, 'size') ? $asset->size() : null;
        } catch (Throwable) {
            $size = null;
        }

        return [
            'id' => $asset->id(),
            'wpId' => $this->wpId($asset->id()),
            'statamicId' => $asset->id(),
            'url' => $url,
            'source_url' => $url,
            'link' => $url,
            'thumbnail' => $thumbnail,
            'alt' => $asset->get('alt', ''),
            'alt_text' => $asset->get('alt', ''),
            'caption' => $asset->get('caption', ''),
            'title' => $asset->get('title', $asset->basename()),
            'filename' => $asset->basename(),
            'container' => $asset->containerHandle(),
            'container_handle' => $asset->containerHandle(),
            'path' => $asset->path(),
            'folder' => $asset->folder(),
            'extension' => $asset->extension(),
            'mime' => $asset->mimeType(),
            'mime_type' => $asset->mimeType(),
            'type' => $mediaType,
            'media_type' => $mediaType,
            'filesize' => $size,
            'width' => $width,
            'height' => $height,
            'sizes' => $this->imageSizes($url, $mediaType, $width, $height),
            'media_details' => [
                'width' => $width,
                'height' => $height,
                'filesize' => $size,
                'sizes' => $this->mediaDetailSizes($url, $mediaType, $width, $height),
            ],
        ];
    }

    private function rawString(mixed $value): string
    {
        if (is_array($value)) {
            $value = $value['raw'] ?? $value['rendered'] ?? '';
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private function wpId(string $id): int
    {
        return (int) (sprintf('%u', crc32($id)) % 2147480000) ?: 1;
    }

    private function mediaType($asset): string
    {
        return match (true) {
            $asset->isImage() || $asset->isSvg() => 'image',
            $asset->isAudio() => 'audio',
            $asset->isVideo() => 'video',
            default => 'file',
        };
    }

    private function imageSizes(?string $url, string $mediaType, mixed $width = null, mixed $height = null): array
    {
        if ($mediaType !== 'image' || ! $url) {
            return [];
        }

        return [
            'full' => ['url' => $url, 'width' => $width, 'height' => $height],
            'large' => ['url' => $url, 'width' => $width, 'height' => $height],
        ];
    }

    private function mediaDetailSizes(?string $url, string $mediaType, mixed $width = null, mixed $height = null): array
    {
        if ($mediaType !== 'image' || ! $url) {
            return [];
        }

        return [
            'full' => ['source_url' => $url, 'width' => $width, 'height' => $height],
            'large' => ['source_url' => $url, 'width' => $width, 'height' => $height],
        ];
    }

    private function sameSchemeUrl(?string $url, Request $request): ?string
    {
        if (! $url) {
            return $url;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if ($host && $host === $request->getHost()) {
            return preg_replace('/^https?:\/\//', $request->getScheme().'://', $url);
        }

        return $url;
    }
}
