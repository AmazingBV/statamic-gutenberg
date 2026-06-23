<?php

namespace Amazingbv\StatamicGutenberg\Http\Controllers\CP;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Statamic\Http\Controllers\CP\CpController;

class EditorController extends CpController
{
    public function __invoke(Request $request): View
    {
        return view('statamic-gutenberg::editor', [
            'channel' => (string) $request->query('channel', ''),
            'title' => (string) $request->query('title', 'Block Editor'),
        ]);
    }
}
