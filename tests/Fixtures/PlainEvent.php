<?php

declare(strict_types=1);

namespace InertiaLive\Tests\Fixtures;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

final class PlainEvent extends ShouldBeStored
{
    public function __construct(public readonly string $documentUuid) {}
}
