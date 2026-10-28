<?php

declare(strict_types=1);

namespace InertiaLive;

/**
 * @internal
 */
final readonly class ResolvedTopic
{
    /**
     * @param  list<string>  $props
     */
    public function __construct(
        public string $topic,
        public array $props,
    ) {}
}
