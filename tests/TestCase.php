<?php

namespace Amazingbv\StatamicGutenberg\Tests;

use Amazingbv\StatamicGutenberg\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;
}
