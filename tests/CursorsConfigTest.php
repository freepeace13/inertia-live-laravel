<?php

declare(strict_types=1);

namespace InertiaLive\Tests;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InertiaLive\Facades\Live;
use InertiaLive\InertiaLiveServiceProvider;

final class CursorsConfigTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        tap($app['config'], function (Repository $config) {
            $config->set('inertia-live.cursors.path', 'live/where');
            $config->set('inertia-live.cursors.middleware', ['web', 'throttle:2,1']);
        });
    }

    public function test_the_path_and_middleware_come_from_config(): void
    {
        Live::publicTopic('stats.{id}');

        $this->assertSame('/live/where', route('inertia-live.cursors', [], false));
        $this->getJson('/live/where?topics[]=stats.1')->assertOk()->assertExactJson(['cursors' => ['stats.1' => 0]]);
        $this->getJson('/inertia-live/cursors?topics[]=stats.1')->assertNotFound();

        $this->getJson('/live/where?topics[]=stats.1')->assertOk();
        $this->getJson('/live/where?topics[]=stats.1')->assertStatus(429);
    }

    public function test_the_config_file_is_publishable(): void
    {
        $paths = ServiceProvider::pathsToPublish(InertiaLiveServiceProvider::class, 'inertia-live-config');

        $this->assertCount(1, $paths);
        $this->assertFileExists((string) array_key_first($paths));
    }
}
