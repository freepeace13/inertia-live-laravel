<?php

declare(strict_types=1);

namespace InertiaLive;

use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Renders an Inertia page whose live props are marked with `live()`. The marked props are
 * unwrapped before Inertia sees them, and the `_live` bindings are derived from them.
 */
final class InertiaLive
{
    /**
     * @param  array<string, mixed>  $props
     */
    public static function render(string $component, array $props = []): Response
    {
        $topics = [];

        foreach ($props as $key => $prop) {
            if (! $prop instanceof LiveProp) {
                continue;
            }

            if (! is_string($key)) {
                throw new InvalidArgumentException("live('{$prop->topic}') needs a named prop; got the position {$key}.");
            }

            // A prop belongs to one topic; wrapping a live prop in another would silently drop the first.
            if ($prop->value instanceof LiveProp) {
                throw new InvalidArgumentException("The prop '{$key}' is wrapped in live() twice; a prop can depend on only one topic.");
            }

            $topics[$prop->topic][] = $key;
            $props[$key] = $prop->value;
        }

        $response = Inertia::render($component, $props);

        foreach ($topics as $topic => $only) {
            LiveResponseMacro::bind($response, (string) $topic, $only);
        }

        return $response;
    }
}
