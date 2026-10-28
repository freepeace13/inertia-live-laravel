<?php

declare(strict_types=1);

namespace InertiaLive\Tests;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Inertia\ServiceProvider as InertiaServiceProvider;
use InertiaLive\InertiaLiveServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\EventSourcing\EventSourcingServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            InertiaServiceProvider::class,
            EventSourcingServiceProvider::class,
            InertiaLiveServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        tap($app['config'], function (Repository $config) {
            $config->set('database.default', 'testing');
            $config->set('cache.default', 'array');
            $config->set('broadcasting.default', 'null');
            $config->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        });
    }

    protected function defineDatabaseMigrations(): void
    {
        $migration = require __DIR__.'/../vendor/spatie/laravel-event-sourcing/database/migrations/create_stored_events_table.php.stub';
        $migration->up();
    }
}
