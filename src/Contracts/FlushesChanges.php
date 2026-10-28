<?php

declare(strict_types=1);

namespace InertiaLive\Contracts;

/**
 * @internal
 */
interface FlushesChanges
{
    public function flush(): void;
}
