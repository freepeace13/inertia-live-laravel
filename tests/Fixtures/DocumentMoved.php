<?php

declare(strict_types=1);

namespace InertiaLive\Tests\Fixtures;

use InertiaLive\Attributes\LiveTopic;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

#[LiveTopic('documents.{documentUuid}', props: ['document'])]
#[LiveTopic('workspaces.{workspaceId}', props: ['documents'])]
final class DocumentMoved extends ShouldBeStored
{
    public function __construct(
        public readonly string $documentUuid,
        public readonly int $workspaceId,
    ) {}
}
