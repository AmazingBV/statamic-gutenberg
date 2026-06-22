<?php

namespace Amazingbv\StatamicGutenberg\Http\Controllers\CP;

use Facades\Statamic\Fields\Validator as FieldValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Statamic\Assets\AssetUploader;
use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Facades\AssetContainer;
use Statamic\Http\Controllers\CP\CpController;
use Statamic\Rules\AllowedFile;
use Statamic\Rules\UploadableAssetPath;
use Throwable;

class AssetsController extends CpController
{
    public function index(Request $request): JsonResponse
    {
        $handle = $request->query('container', config('statamic-gutenberg.assets_container', 'assets'));
        $container = AssetContainer::find($handle);

        if (! $container) {
            return response()->json(['data' => [], 'folders' => []]);
        }

        $type = $this->normalType((string) $request->query('type', 'image'));
        $folder = $this->normalFolder((string) $request->query('folder', '/'));
        $search = strtolower(trim((string) $request->query('q', '')));

        $assets = $container->assets($folder, false)
            ->filter(fn ($asset) => $this->assetMatchesType($asset, $type))
            ->filter(function ($asset) use ($search) {
                if ($search === '') {
                    return true;
                }

                return str_contains(strtolower($asset->basename()), $search)
                    || str_contains(strtolower((string) $asset->get('alt')), $search)
                    || str_contains(strtolower($asset->path()), $search);
            })
            ->take(80)
            ->values()
            ->map(fn ($asset) => $this->assetPayload($asset, $request))
            ->all();

        $folders = $container->assetFolders($folder, false)
            ->values()
            ->map(fn ($folder) => $this->folderPayload($folder))
            ->all();

        return response()->json([
            'data' => $assets,
            'folders' => $folders,
            'folder' => $folder,
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $handle = $request->input('container', $request->query('container', config('statamic-gutenberg.assets_container', 'assets')));
        $container = AssetContainer::find($handle);

        abort_unless($container, 404);

        $this->authorize('store', [AssetContract::class, $container]);

        $type = $this->normalType((string) $request->input('type', $request->query('type', 'image')));
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

        if (! $this->assetMatchesType($asset, $type)) {
            $asset->delete();

            throw ValidationException::withMessages([
                'file' => __('The uploaded file is not allowed for this block.'),
            ]);
        }

        return response()->json(['data' => $this->assetPayload($asset, $request)], 201);
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

    private function assetMatchesType($asset, string $type): bool
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

        try {
            $thumbnail = $asset->isImage() || $asset->isSvg()
                ? $this->sameSchemeUrl($asset->thumbnailUrl('small'), $request)
                : null;
        } catch (Throwable) {
            $thumbnail = null;
        }

        return [
            'id' => $asset->id(),
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
            'path' => $asset->path(),
            'folder' => $asset->folder(),
            'extension' => $asset->extension(),
            'mime' => $asset->mimeType(),
            'mime_type' => $asset->mimeType(),
            'type' => $mediaType,
            'media_type' => $mediaType,
            'sizes' => $this->imageSizes($url, $mediaType),
            'media_details' => [
                'sizes' => $this->mediaDetailSizes($url, $mediaType),
            ],
        ];
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

    private function imageSizes(?string $url, string $mediaType): array
    {
        if ($mediaType !== 'image' || ! $url) {
            return [];
        }

        return [
            'full' => ['url' => $url],
            'large' => ['url' => $url],
        ];
    }

    private function mediaDetailSizes(?string $url, string $mediaType): array
    {
        if ($mediaType !== 'image' || ! $url) {
            return [];
        }

        return [
            'full' => ['source_url' => $url],
            'large' => ['source_url' => $url],
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
