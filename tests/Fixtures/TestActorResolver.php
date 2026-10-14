<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\Tests\Fixtures;

use MikhailMelnikov\JobsMonitor\Contracts\ActorResolver;

/**
 * Actor resolver with a fixed user id — stands in for an admin panel integration.
 */
class TestActorResolver implements ActorResolver
{
    /**
     * Fixed user id.
     */
    public function id(): int|string|null
    {
        return 42;
    }
}
