<?php

declare(strict_types=1);

namespace BlobSolutions\LaravelVcrAm\Tests;

use BlobSolutions\LaravelVcrAm\Facades\VcrAm;
use BlobSolutions\LaravelVcrAm\VcrAmServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     *
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [VcrAmServiceProvider::class];
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     *
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return ['VcrAm' => VcrAm::class];
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('vcr-am.api_key', 'test-api-key');
        $app['config']->set('vcr-am.base_url', null);
    }
}
