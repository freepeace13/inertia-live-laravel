<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use InertiaLive\Broadcasting\LiveChangeBroadcast;
use InertiaLive\Change;
use InertiaLive\ChangeBuffer;
use InertiaLive\Contracts\FlushesChanges;
use InertiaLive\Facades\Live;
use InertiaLive\Tests\Fixtures\DocumentProjector;
use InertiaLive\Tests\Fixtures\DocumentRenamed;
use InertiaLive\Tests\Fixtures\QueuedDocumentProjector;
use Spatie\EventSourcing\Facades\Projectionist;

beforeEach(function () {
    Live::authorize('documents.{uuid}', fn () => true);
});

it('keeps signals from a sync and a queued projector on one topic distinct and increasing', function () {
    Event::fake([LiveChangeBroadcast::class]);
    config(['queue.default' => 'sync']);
    Projectionist::addProjector(DocumentProjector::class);
    Projectionist::addProjector(QueuedDocumentProjector::class);

    // Both projectors handle the event; their changes coalesce into the request's single signal.
    event(new DocumentRenamed('abc', 'one'));
    app(FlushesChanges::class)->flush();
    // The queued projector's job finishing later is a second flush on the same topic.
    app(ChangeBuffer::class)->add(new Change('documents.abc', ['document']));
    app(FlushesChanges::class)->flush();

    $versions = [];
    Event::assertDispatched(LiveChangeBroadcast::class, function (LiveChangeBroadcast $e) use (&$versions) {
        $versions[] = $e->version;

        return true;
    });

    expect($versions)->toHaveCount(2)
        ->and($versions[1])->toBeGreaterThan($versions[0]);
});

it('excludes the sender socket from the broadcast', function () {
    request()->headers->set('X-Socket-ID', '123.456');

    $broadcast = new LiveChangeBroadcast(new Change('documents.abc'), 1);

    expect($broadcast->socket)->toBe('123.456');
});
