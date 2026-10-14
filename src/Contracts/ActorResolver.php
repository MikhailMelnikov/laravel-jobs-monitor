<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\Contracts;

/**
 * Resolves the user who performs a manual action (run, retry, delete, flush).
 *
 * The core knows nothing about admin panels: each integration (Backpack,
 * Filament, …) binds its own implementation that reads its own guard.
 */
interface ActorResolver
{
    /**
     * Identifier of the current user, or null when nobody is authenticated.
     */
    public function id(): int|string|null;
}
