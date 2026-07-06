<?php

namespace Amazingbv\StatamicGutenberg\Http\Controllers\CP;

use Amazingbv\StatamicGutenberg\Blocks\BlockRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Statamic\Http\Controllers\CP\CpController;
use Statamic\Facades\User;

class BlockRendererController extends CpController
{
    public function __invoke(Request $request, BlockRenderer $renderer): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'regex:/^[a-z0-9][a-z0-9-]*\/[a-z0-9][a-z0-9-]*$/'],
            'attributes' => ['nullable', 'array'],
            'content' => ['nullable', 'string'],
            'allowed_blocks' => ['nullable', 'array'],
            'allowed_blocks.*' => ['string', 'regex:/^[a-z0-9][a-z0-9-]*\/[a-z0-9][a-z0-9-]*$/'],
        ]);

        $name = $validated['name'];
        $attributes = $validated['attributes'] ?? [];
        $content = $validated['content'] ?? '';
        $allowedBlocks = $validated['allowed_blocks'] ?? null;
        $encodedAttributes = $attributes === []
            ? ''
            : ' '.json_encode($attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $serialized = sprintf(
            '<!-- wp:%s%s -->%s<!-- /wp:%s -->',
            $name,
            $encodedAttributes,
            $content,
            $name
        );

        return response()->json([
            'rendered' => (string) $renderer->render($serialized, array_filter([
                'allowed_blocks' => $allowedBlocks,
                'user' => User::current(),
            ], fn ($value) => $value !== null)),
        ]);
    }
}
