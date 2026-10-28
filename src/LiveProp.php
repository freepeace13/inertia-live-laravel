<?php

declare(strict_types=1);

namespace InertiaLive;

/**
 * A page prop marked as depending on a topic. Created by `live()` and unwrapped by
 * `InertiaLive::render()`; it never reaches Inertia.
 *
 * @internal
 */
final readonly class LiveProp
{
    public function __construct(
        public string $topic,
        public mixed $value,
    ) {}
}
