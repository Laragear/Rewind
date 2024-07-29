<?php

namespace Tests;

use Laragear\Rewind\RewindServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [RewindServiceProvider::class];
    }
}
