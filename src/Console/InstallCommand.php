<?php

declare(strict_types=1);

namespace InertiaLive\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Sets up the server side: Spatie's migrations, the config, a check of broadcasting and the
 * cursor cache, and the client snippet to paste into the app.
 *
 * @internal Run it through `php artisan inertia-live:install`; the class is not API.
 */
final class InstallCommand extends Command
{
    protected $signature = 'inertia-live:install
        {--client= : Print the client setup for "vue" or "react" (default: both)}
        {--skip-migrations : Do not publish or run the event sourcing migrations}
        {--force : Overwrite an already published config file}';

    protected $description = 'Set up Inertia Live: migrations, config, broadcasting checks and the client snippet';

    public function handle(): int
    {
        $client = $this->option('client');

        if ($client !== null && ! in_array($client, ['vue', 'react'], true)) {
            $this->components->error('--client must be "vue" or "react".');

            return self::FAILURE;
        }

        if (! $this->option('skip-migrations')) {
            $this->migrations();
        }

        $this->config();

        $problems = $this->checks();

        $this->clientSetup($client);

        $this->newLine();
        $problems === 0
            ? $this->components->info('Inertia Live is installed.')
            : $this->components->warn("Inertia Live is installed, with {$problems} thing(s) to fix above.");

        return self::SUCCESS;
    }

    private function migrations(): void
    {
        if (Schema::hasTable('stored_events')) {
            $this->components->twoColumnDetail('Event sourcing migrations', 'already applied');

            return;
        }

        $this->call('vendor:publish', [
            '--provider' => 'Spatie\\EventSourcing\\EventSourcingServiceProvider',
            '--tag' => 'event-sourcing-migrations',
        ]);
        $this->call('migrate');
    }

    private function config(): void
    {
        if (is_file(config_path('inertia-live.php')) && ! $this->option('force')) {
            $this->components->twoColumnDetail('Config', 'already published (use --force to overwrite)');

            return;
        }

        $this->call('vendor:publish', array_filter([
            '--tag' => 'inertia-live-config',
            '--force' => $this->option('force') ?: null,
        ]));
    }

    /**
     * @return int How many problems were reported.
     */
    private function checks(): int
    {
        $this->newLine();
        $this->components->info('Checking the setup');

        $problems = 0;
        $report = function (bool $ok, string $label, string $fix) use (&$problems): void {
            $this->components->twoColumnDetail($label, $ok ? '<fg=green>ok</>' : '<fg=yellow>check</>');

            if (! $ok) {
                $this->line("    {$fix}");
                $problems++;
            }
        };

        $broadcaster = (string) config('broadcasting.default');
        $report(
            ! in_array($broadcaster, ['', 'null', 'log'], true),
            "Broadcaster ({$broadcaster})",
            'Signals never reach browsers with this broadcaster. Run `php artisan install:broadcasting` and set BROADCAST_CONNECTION (reverb, pusher or ably).',
        );

        $report(
            $this->hasChannelAuthRoute(),
            'Channel authorization route',
            'Private channels cannot be authorized. Run `php artisan install:broadcasting`, or enable broadcasting routes.',
        );

        $store = (string) (config('inertia-live.cursor_store') ?? config('cache.default'));
        $driver = (string) config("cache.stores.{$store}.driver");
        $report(
            ! in_array($driver, ['file', 'null'], true),
            "Cursor cache ({$store}: {$driver})",
            'Cursors need an atomic increment. Use redis, database or memcached, or point INERTIA_LIVE_CURSOR_STORE at such a store.',
        );

        return $problems;
    }

    private function hasChannelAuthRoute(): bool
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (trim($route->uri(), '/') === 'broadcasting/auth') {
                return true;
            }
        }

        return false;
    }

    private function clientSetup(?string $client): void
    {
        $this->newLine();
        $this->components->info('Client setup');

        foreach ($client === null ? ['vue', 'react'] : [$client] as $name) {
            $this->line($name === 'vue' ? $this->vueSnippet() : $this->reactSnippet());
            $this->newLine();
        }

        $this->line('Docs: https://github.com/freepeace13/inertia-live-docs/blob/main/v1/installation.md');
    }

    private function vueSnippet(): string
    {
        return <<<'TXT'
        Vue:
          npm install @freepeace13/inertia-live-vue @laravel/echo-vue laravel-echo pusher-js

          // app.ts: after `.use(plugin)`; `configureEcho()` comes from `install:broadcasting`
          import { echo } from '@laravel/echo-vue'
          import { InertiaLive } from '@freepeace13/inertia-live-vue'

          .use(InertiaLive, { echo: echo() })
        TXT;
    }

    private function reactSnippet(): string
    {
        return <<<'TXT'
        React:
          npm install @freepeace13/inertia-live-react @laravel/echo-react laravel-echo pusher-js

          // a persistent layout, inside the Inertia tree
          import { echo } from '@laravel/echo-react'
          import { InertiaLiveProvider } from '@freepeace13/inertia-live-react'

          <InertiaLiveProvider echo={echo()}>{children}</InertiaLiveProvider>
        TXT;
    }
}
