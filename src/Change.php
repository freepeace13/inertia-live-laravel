<?php

declare(strict_types=1);

namespace InertiaLive;

/**
 * A topic whose read model changed. It carries no version: the flusher assigns the topic's
 * next sequence number when it broadcasts, so every signal is newer than the one before.
 *
 * @internal
 */
final readonly class Change
{
    /**
     * @param  list<string>  $props
     */
    public function __construct(
        public string $topic,
        public array $props = [],
    ) {}
}
