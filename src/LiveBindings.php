<?php

declare(strict_types=1);

namespace InertiaLive;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Route;
use InertiaLive\Cursor\CursorRepository;

/**
 * The `_live` prop for one Inertia response. Resolved lazily so every render,
 * including partial reloads, reads fresh cursors.
 *
 * @internal
 */
final class LiveBindings
{
    /** @var list<array{topic: string, props: list<string>}> */
    private array $bindings = [];

    public function __construct(
        private readonly CursorRepository $cursors,
        private readonly Config $config,
        private readonly LiveManager $live,
    ) {}

    /**
     * @param  list<string>  $props
     */
    public function add(string $topic, array $props): void
    {
        $this->bindings[] = ['topic' => $topic, 'props' => $props];
    }

    /**
     * @return array{bindings: list<array{topic: string, channel: string, props: list<string>, cursor: int, public: bool}>, cursors?: string}
     */
    public function __invoke(): array
    {
        $prefix = (string) $this->config->get('inertia-live.channel_prefix', 'live');

        $live = [
            'bindings' => array_map(fn (array $binding) => [
                'topic' => $binding['topic'],
                'channel' => $prefix.'.'.$binding['topic'],
                'props' => $binding['props'],
                'cursor' => $this->cursors->get($binding['topic']),
                'public' => $this->live->isPublic($binding['topic']),
            ], $this->bindings),
        ];

        // A same-origin path the client asks for fresh cursors; without it the client re-reads `_live`.
        if (Route::has('inertia-live.cursors')) {
            $live['cursors'] = route('inertia-live.cursors', [], false);
        }

        return $live;
    }
}
