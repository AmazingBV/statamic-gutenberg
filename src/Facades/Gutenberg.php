<?php

namespace Amazingbv\StatamicGutenberg\Facades;

use Illuminate\Support\Facades\Facade;

class Gutenberg extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'statamic-gutenberg';
    }
}
