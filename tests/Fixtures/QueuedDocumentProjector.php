<?php

declare(strict_types=1);

namespace InertiaLive\Tests\Fixtures;

use Illuminate\Contracts\Queue\ShouldQueue;
use InertiaLive\Concerns\EmitsLiveChanges;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

final class QueuedDocumentProjector extends Projector implements ShouldQueue
{
    use EmitsLiveChanges;

    public function onDocumentRenamed(DocumentRenamed $event): void {}
}
