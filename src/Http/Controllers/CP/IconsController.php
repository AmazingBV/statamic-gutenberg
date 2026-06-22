<?php

namespace Amazingbv\StatamicGutenberg\Http\Controllers\CP;

use Amazingbv\StatamicGutenberg\Icons\IconRepository;
use Illuminate\Http\JsonResponse;
use Statamic\Http\Controllers\CP\CpController;

class IconsController extends CpController
{
    public function index(IconRepository $icons): JsonResponse
    {
        return response()->json([
            'data' => $icons->all(),
        ]);
    }
}
