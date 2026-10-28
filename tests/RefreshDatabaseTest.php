<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use InertiaLive\Broadcasting\LiveChangeBroadcast;
use InertiaLive\Change;
use InertiaLive\ChangeBuffer;
use InertiaLive\ChangeFlusher;
use InertiaLive\Facades\Live;

uses(DatabaseTransactions::class);

it('still broadcasts inside the wrapping transaction of DatabaseTransactions tests', function () {
    Event::fake([LiveChangeBroadcast::class]);
    Live::authorize('documents.{uuid}', fn () => true);

    app(ChangeBuffer::class)->add(new Change('documents.abc', ['document']));
    app(ChangeFlusher::class)->flush();

    Event::assertDispatchedTimes(LiveChangeBroadcast::class, 1);
});
