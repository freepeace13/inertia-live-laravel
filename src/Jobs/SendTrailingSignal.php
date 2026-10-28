<?php

declare(strict_types=1);

namespace InertiaLive\Jobs;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use InertiaLive\Broadcasting\LiveChangeBroadcast;
use InertiaLive\Change;
use InertiaLive\Cursor\CursorRepository;
use InertiaLive\LiveManager;

/**
 * Sent once a rate-limit window ends, so a burst never leaves open pages stale: it
 * announces the topic's current cursor with no prop list, which clients read as
 * "something changed, reload everything you bound".
 *
 * @internal Queued job: do not rename it or change its constructor while jobs may be in flight.
 */
final class SendTrailingSignal implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    public function __construct(public readonly string $topic) {}

    public function handle(CursorRepository $cursors, LiveManager $live, Dispatcher $events): void
    {
        $version = $cursors->get($this->topic);

        if ($version < 1) {
            return;
        }

        $events->dispatch(new LiveChangeBroadcast(new Change($this->topic), $version, $live->isPublic($this->topic)));
    }
}
