<?php

namespace FilaHQ\Statify\Tests;

use FilaHQ\Statify\StatifyServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        $providers = [StatifyServiceProvider::class];

        if (class_exists(SanctumServiceProvider::class)) {
            $providers[] = SanctumServiceProvider::class;
        }

        return $providers;
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('statify.guard', 'public');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
