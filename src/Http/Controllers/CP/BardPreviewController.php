<?php

namespace Amazingbv\StatamicGutenberg\Http\Controllers\CP;

use Amazingbv\StatamicGutenberg\Blocks\BlockRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Statamic\Http\Controllers\CP\CpController;

class BardPreviewController extends CpController
{
    public function __invoke(Request $request, BlockRenderer $renderer): JsonResponse
    {
        $validated = $request->validate([
            'block' => ['required', 'string', 'regex:/^[a-z0-9][a-z0-9-]*\/[a-z0-9][a-z0-9-]*$/'],
            'set' => ['required', 'string'],
            'source' => ['required', 'string'],
            'values' => ['nullable', 'array'],
        ]);

        $attributes = [
            'bardSet' => $validated['set'],
            'bardSource' => $validated['source'],
            'values' => $validated['values'] ?? [],
        ];
        $encodedAttributes = ' '.json_encode($attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $serialized = sprintf(
            '<!-- wp:%s%s /-->',
            $validated['block'],
            $encodedAttributes
        );

        return response()->json([
            'html' => (string) $renderer->render($serialized, [
                'allowed_blocks' => [$validated['block']],
            ]),
        ]);
    }
}
