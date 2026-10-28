<?php

declare(strict_types=1);

namespace InertiaLive\Testing;

use Illuminate\Http\Response as HttpResponse;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Assert as PHPUnit;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Registers `assertLive()` and `assertNotLive()` on the `assertInertia()` callback argument and
 * on the test response, so controller tests can check which props a page keeps live.
 *
 * @internal
 */
final class LiveAssertions
{
    public static function register(): void
    {
        if (! class_exists(AssertableInertia::class)) {
            return;
        }

        AssertableInertia::macro('assertLive', function (string $topic, ?array $props = null): AssertableInertia {
            /** @var AssertableInertia $this */
            LiveAssertions::assertBound(LiveAssertions::bindingsOf($this->toArray()), $topic, $props);

            return $this;
        });

        AssertableInertia::macro('assertNotLive', function (?string $topic = null): AssertableInertia {
            /** @var AssertableInertia $this */
            LiveAssertions::assertUnbound(LiveAssertions::bindingsOf($this->toArray()), $topic);

            return $this;
        });

        TestResponse::macro('assertLive', function (string $topic, ?array $props = null): TestResponse {
            /** @var TestResponse<SymfonyResponse> $this */
            LiveAssertions::assertBound(LiveAssertions::bindingsOf(LiveAssertions::pageOf($this)), $topic, $props);

            return $this;
        });

        TestResponse::macro('assertNotLive', function (?string $topic = null): TestResponse {
            /** @var TestResponse<SymfonyResponse> $this */
            LiveAssertions::assertUnbound(LiveAssertions::bindingsOf(LiveAssertions::pageOf($this)), $topic);

            return $this;
        });
    }

    /**
     * The Inertia page object of a response: the JSON body of an Inertia request, otherwise the page in the root view.
     *
     * @param  TestResponse<SymfonyResponse>  $response
     * @return array<string, mixed>
     */
    public static function pageOf(TestResponse $response): array
    {
        if ($response->headers->has('X-Inertia')) {
            /** @var array<string, mixed> */
            return $response->json();
        }

        /** @var TestResponse<HttpResponse> $response */
        return AssertableInertia::fromTestResponse($response)->toArray();
    }

    /**
     * @param  array<string, mixed>  $page
     * @return list<array{topic: string, props: list<string>}>
     */
    public static function bindingsOf(array $page): array
    {
        /** @var list<array{topic: string, props: list<string>}> */
        return $page['props']['_live']['bindings'] ?? [];
    }

    /**
     * @param  list<array{topic: string, props: list<string>}>  $bindings
     * @param  list<string>|null  $props  The props the binding must list, in any order.
     */
    public static function assertBound(array $bindings, string $topic, ?array $props): void
    {
        $matching = array_values(array_filter($bindings, fn (array $binding) => $binding['topic'] === $topic));

        PHPUnit::assertNotEmpty(
            $matching,
            "Expected the page to be live on topic [{$topic}]; its topics are [".implode(', ', array_column($bindings, 'topic')).'].',
        );

        if ($props === null) {
            return;
        }

        $actual = $matching[0]['props'];
        sort($actual);
        $expected = array_values($props);
        sort($expected);

        PHPUnit::assertSame(
            $expected,
            $actual,
            "Topic [{$topic}] is bound to props [".implode(', ', $matching[0]['props']).'], not ['.implode(', ', $props).'].',
        );
    }

    /**
     * @param  list<array{topic: string, props: list<string>}>  $bindings
     */
    public static function assertUnbound(array $bindings, ?string $topic): void
    {
        $topics = array_column($bindings, 'topic');

        if ($topic === null) {
            PHPUnit::assertEmpty($topics, 'Expected the page to have no live bindings; it is live on ['.implode(', ', $topics).'].');

            return;
        }

        PHPUnit::assertNotContains($topic, $topics, "Expected the page not to be live on topic [{$topic}].");
    }
}
