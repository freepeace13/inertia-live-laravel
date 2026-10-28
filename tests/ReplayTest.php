<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use InertiaLive\Broadcasting\LiveChangeBroadcast;
use InertiaLive\ChangeBuffer;
use InertiaLive\Cursor\CursorRepository;
use InertiaLive\Facades\Live;
use InertiaLive\Tests\Fixtures\DocumentProjector;
use InertiaLive\Tests\Fixtures\DocumentRenamed;
use Spatie\EventSourcing\Facades\Projectionist;
use Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent;

beforeEach(function () {
    Live::authorize('documents.{uuid}', fn () => true);
    // Store events with no handlers registered so the replay is the only thing that projects them.
    Projectionist::withoutEventHandlers();

    collect(['one', 'two', 'three'])->each(fn (string $title) => event(new DocumentRenamed('abc', $title)));

    Projectionist::addProjector(DocumentProjector::class);

    expect(EloquentStoredEvent::count())->toBe(3);
    app(ChangeBuffer::class)->drain();

    Event::fake([LiveChangeBroadcast::class]);
});

it('suppresses signals while replaying', function () {
    Projectionist::replay(collect([app(DocumentProjector::class)]));

    Event::assertNotDispatched(LiveChangeBroadcast::class);
    expect(app(ChangeBuffer::class)->isEmpty())->toBeTrue();
});

it('emits one final signal per topic after the replay, newer than any cursor pages hold', function () {
    config(['inertia-live.replay.final_signal' => true]);
    $heldByOpenPages = app(CursorRepository::class)->next('documents.abc');

    Projectionist::replay(collect([app(DocumentProjector::class)]));

    Event::assertDispatchedTimes(LiveChangeBroadcast::class, 1);
    Event::assertDispatched(
        LiveChangeBroadcast::class,
        fn (LiveChangeBroadcast $e) => $e->change->topic === 'documents.abc' && $e->version > $heldByOpenPages,
    );
});

it('emits normally during a replay when suppression is off', function () {
    config(['inertia-live.replay.suppress' => false]);

    Projectionist::replay(collect([app(DocumentProjector::class)]));

    // Signals coalesce to one per topic at the next flush.
    Event::assertDispatchedTimes(LiveChangeBroadcast::class, 1);
});
