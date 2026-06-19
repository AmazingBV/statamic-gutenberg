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

class AssetsController extends CpController
{
    public function index(Request $request): JsonResponse
    {
        $handle = $request->query('container', config('statamic-gutenberg.assets_container', 'assets'));
        $container = AssetContainer::find($handle);

        if (! $container) {
            return response()->json(['data' => []]);
        }

        $search = strtolower(trim((string) $request->query('q', '')));

        $assets = $container->queryAssets()
            ->get()
            ->filter(fn ($asset) => $asset->isImage())
            ->filter(function ($asset) use ($search) {
                if ($search === '') {
                    return true;
                }

                return str_contains(strtolower($asset->basename()), $search)
                    || str_contains(strtolower((string) $asset->get('alt')), $search);
            })
            ->take(60)
            ->values()
            ->map(fn ($asset) => $this->assetPayload($asset, $request));

        return response()->json(['data' => $assets]);
    }

    public function upload(Request $request): JsonResponse
    {
        $handle = $request->input('container', $request->query('container', config('statamic-gutenberg.assets_container', 'assets')));
        $container = AssetContainer::find($handle);

        abort_unless($container, 404);

        $this->authorize('store', [AssetContract::class, $container]);

        $validationRules = collect($container->validationRules())
            ->map(fn ($rule) => FieldValidator::parse($rule))
            ->all();

        $request->validate([
            'file' => array_merge(['required', 'file', 'image', new AllowedFile], $validationRules),
        ]);

        $file = $request->file('file');
        $path = $this->uniquePath(
            $container,
            $request->input('folder', ''),
            $file->getClientOriginalName()
        );

        Validator::make(['path' => $path], ['path' => new UploadableAssetPath($container)])->validate();

        $asset = $container->makeAsset($path)->upload($file);

        if (! $asset->isImage()) {
            $asset->delete();

            throw ValidationException::withMessages([
                'file' => __('The uploaded file must be an image.'),
            ]);
        }

        return response()->json(['data' => $this->assetPayload($asset, $request)], 201);
    }

    private function uniquePath($container, string $folder, string $originalName): string
    {
        $folder = trim(AssetUploader::getSafePath($folder), '/');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $filename = AssetUploader::getSafeFilename(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'image';
        $candidate = $filename.'.'.$extension;
        $path = ltrim(($folder ? $folder.'/' : '').$candidate, '/');
        $index = 2;

        while ($container->asset($path)) {
            $candidate = "{$filename}-{$index}.{$extension}";
            $path = ltrim(($folder ? $folder.'/' : '').$candidate, '/');
            $index++;
        }

        return $path;
    }

    private function assetPayload($asset, Request $request): array
    {
        return [
            'id' => $asset->id(),
            'url' => $this->sameSchemeUrl($asset->url(), $request),
            'alt' => $asset->get('alt', ''),
            'title' => $asset->get('title', $asset->basename()),
            'filename' => $asset->basename(),
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
