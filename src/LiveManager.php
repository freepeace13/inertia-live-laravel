<?php

declare(strict_types=1);

namespace InertiaLive;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * @internal
 */
final class LiveManager
{
    /** @var list<string> */
    private array $privatePatterns = [];

    /** @var list<string> */
    private array $publicPatterns = [];

    /** @var array<string, Closure> */
    private array $authorizers = [];

    public function __construct(private readonly Config $config) {}

    /**
     * Authorize subscriptions to a private topic pattern, e.g. `documents.{uuid}`.
     * The callback receives the user followed by the pattern's parameters.
     */
    public function authorize(string $pattern, Closure $callback): void
    {
        $this->assertNotRegisteredAs($pattern, $this->publicPatterns, 'public');

        $this->privatePatterns[] = $pattern;
        $this->authorizers[$pattern] = $callback;

        Broadcast::channel($this->config->get('inertia-live.channel_prefix', 'live').'.'.$pattern, $callback);
    }

    /**
     * Declare a topic pattern public: anyone can subscribe, so it needs no authorizer.
     * Visibility lives here, once per pattern, rather than on each attribute or binding.
     */
    public function publicTopic(string $pattern): void
    {
        $this->assertNotRegisteredAs($pattern, $this->privatePatterns, 'private');

        $this->publicPatterns[] = $pattern;
    }

    public function isPublic(string $topic): bool
    {
        return $this->matchesAny($this->publicPatterns, $topic);
    }

    /**
     * Whether the user may subscribe to the topic: the same callbacks that authorize the private
     * channel, called with the topic's placeholder values as strings. Public topics need no user.
     * Fails closed: no user, no matching authorizer, or an authorizer that throws all deny.
     */
    public function canSubscribe(?object $user, string $topic): bool
    {
        if ($this->isPublic($topic)) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        foreach ($this->authorizers as $pattern => $callback) {
            $parameters = $this->parametersFor($pattern, $topic);

            if ($parameters === null) {
                continue;
            }

            try {
                return (bool) $callback($user, ...$parameters);
            } catch (Throwable $e) {
                if ($this->config->get('inertia-live.debug')) {
                    Log::debug('Inertia Live authorizer threw while checking a cursor; treated as a denial.', [
                        'topic' => $topic,
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                    ]);
                }

                return false;
            }
        }

        return false;
    }

    public function hasAuthorizerFor(string $topic): bool
    {
        return $this->matchesAny($this->privatePatterns, $topic);
    }

    /**
     * @return list<string>|null The placeholder values in order, or null when the topic does not match.
     */
    private function parametersFor(string $pattern, string $topic): ?array
    {
        $regex = preg_replace('/\\\\\{\w+\\\\\}/', '([^.]+)', preg_quote($pattern, '/'));

        if (preg_match('/^'.$regex.'$/', $topic, $matches) !== 1) {
            return null;
        }

        return array_slice($matches, 1);
    }

    /**
     * @param  list<string>  $others
     */
    private function assertNotRegisteredAs(string $pattern, array $others, string $visibility): void
    {
        if (in_array($pattern, $others, true)) {
            throw new InvalidArgumentException("Topic pattern [{$pattern}] is already registered as {$visibility}.");
        }
    }

    /**
     * @param  list<string>  $patterns
     */
    private function matchesAny(array $patterns, string $topic): bool
    {
        foreach ($patterns as $pattern) {
            $regex = preg_replace('/\\\\\{\w+\\\\\}/', '[^.]+', preg_quote($pattern, '/'));

            if (preg_match('/^'.$regex.'$/', $topic) === 1) {
                return true;
            }
        }

        return false;
    }
}
