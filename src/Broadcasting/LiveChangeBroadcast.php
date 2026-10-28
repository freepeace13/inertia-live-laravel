<?php

declare(strict_types=1);

namespace InertiaLive\Broadcasting;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use InertiaLive\Change;

/**
 * @experimental May change in a 1.x minor.
 */
final class LiveChangeBroadcast implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    /** The wire protocol version this package speaks (see v1/protocol.md in the docs repo). */
    private const PROTOCOL = 1;

    public function __construct(
        public readonly Change $change,
        public readonly int $version,
        public readonly bool $public = false,
    ) {
        // Skip the sender's own socket (X-Socket-ID); they already get fresh props from Inertia.
        $this->dontBroadcastToCurrentUser();
    }

    public function broadcastOn(): Channel
    {
        $name = config('inertia-live.channel_prefix', 'live').'.'.$this->change->topic;

        return $this->public ? new Channel($name) : new PrivateChannel($name);
    }

    public function broadcastAs(): string
    {
        return 'live.changed';
    }

    /**
     * The signal never carries model data: topic, version, affected prop keys and the protocol version.
     *
     * @return array{topic: string, version: int, props: list<string>, protocol: int}
     */
    public function broadcastWith(): array
    {
        return [
            'topic' => $this->change->topic,
            'version' => $this->version,
            'props' => $this->change->props,
            'protocol' => self::PROTOCOL,
        ];
    }
}
