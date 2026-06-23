<?php

namespace Amazingbv\StatamicGutenberg\Http\Controllers\CP;

use Amazingbv\StatamicGutenberg\Patterns\PatternRepository;
use Illuminate\Http\JsonResponse;
use Statamic\Http\Controllers\CP\CpController;

class PatternsController extends CpController
{
    public function index(PatternRepository $patterns): JsonResponse
    {
        return response()->json([
            'data' => $patterns->editorPayload(),
        ]);
    }
}
