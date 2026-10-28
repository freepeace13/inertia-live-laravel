<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->configFile = config_path('inertia-live.php');
    @unlink($this->configFile);
});

afterEach(function () {
    @unlink($this->configFile);
});

it('publishes the config and prints both client setups', function () {
    Schema::shouldReceive('hasTable')->with('stored_events')->andReturn(true);

    $this->artisan('inertia-live:install')
        ->expectsOutputToContain('already applied')
        ->expectsOutputToContain('npm install @freepeace13/inertia-live-vue')
        ->expectsOutputToContain('npm install @freepeace13/inertia-live-react')
        ->assertSuccessful();

    expect(is_file($this->configFile))->toBeTrue();
});

it('prints only the requested client', function () {
    $this->artisan('inertia-live:install', ['--client' => 'react', '--skip-migrations' => true])
        ->expectsOutputToContain('InertiaLiveProvider')
        ->doesntExpectOutputToContain('npm install @freepeace13/inertia-live-vue')
        ->assertSuccessful();
});

it('rejects an unknown client', function () {
    $this->artisan('inertia-live:install', ['--client' => 'svelte'])
        ->expectsOutputToContain('--client must be')
        ->assertFailed();
});

it('keeps a published config unless forced', function () {
    file_put_contents($this->configFile, "<?php\n\nreturn ['marker' => true];\n");

    $this->artisan('inertia-live:install', ['--skip-migrations' => true])
        ->expectsOutputToContain('already published')
        ->assertSuccessful();

    expect(file_get_contents($this->configFile))->toContain('marker');

    $this->artisan('inertia-live:install', ['--skip-migrations' => true, '--force' => true])->assertSuccessful();

    expect(file_get_contents($this->configFile))->not->toContain('marker');
});

it('warns about a broadcaster that delivers nothing', function () {
    config(['broadcasting.default' => 'null']);

    $this->artisan('inertia-live:install', ['--skip-migrations' => true])
        ->expectsOutputToContain('Signals never reach browsers')
        ->expectsOutputToContain('thing(s) to fix')
        ->assertSuccessful();
});

it('warns about a cursor cache without atomic increments', function () {
    config(['cache.default' => 'file', 'cache.stores.file' => ['driver' => 'file', 'path' => sys_get_temp_dir()]]);

    $this->artisan('inertia-live:install', ['--skip-migrations' => true])
        ->expectsOutputToContain('Cursors need an atomic increment')
        ->assertSuccessful();
});

it('reports a clean setup when everything is in place', function () {
    config(['broadcasting.default' => 'reverb']);
    Broadcast::routes();

    $this->artisan('inertia-live:install', ['--skip-migrations' => true])
        ->expectsOutputToContain('Inertia Live is installed.')
        ->doesntExpectOutputToContain('thing(s) to fix')
        ->assertSuccessful();
});
