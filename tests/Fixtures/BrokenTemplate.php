<?php

declare(strict_types=1);

namespace InertiaLive\Tests\Fixtures;

use InertiaLive\Attributes\LiveTopic;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

#[LiveTopic('documents.{missing}', props: ['document'])]
final class BrokenTemplate extends ShouldBeStored
{
    public function __construct(public readonly string $documentUuid) {}
}
