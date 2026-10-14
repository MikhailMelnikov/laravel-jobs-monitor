<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\Support;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use MikhailMelnikov\JobsMonitor\Contracts\ActorResolver;

/**
 * Default actor resolver: the user of a Laravel auth guard.
 */
final class GuardActorResolver implements ActorResolver
{
    /**
     * @param AuthFactory $auth  Auth manager
     * @param string|null $guard Guard name; null — the default guard
     */
    public function __construct(
        private readonly AuthFactory $auth,
        private readonly ?string $guard = null,
    ) {
    }

    /**
     * Identifier of the user authenticated with the configured guard.
     */
    public function id(): int|string|null
    {
        /** @var int|string|null $id */
        $id = $this->auth->guard($this->guard)->id();

        return $id;
    }
}
