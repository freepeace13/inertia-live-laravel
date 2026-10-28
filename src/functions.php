<?php

declare(strict_types=1);

namespace InertiaLive;

if (! function_exists('InertiaLive\live')) {
    /**
     * Mark a prop as depending on a topic, for `InertiaLive::render()`. Pass a value, a closure
     * or any other prop Inertia accepts (`Inertia::optional()`, `Inertia::defer()`, ...).
     */
    function live(string $topic, mixed $value): LiveProp
    {
        return new LiveProp($topic, $value);
    }
}
