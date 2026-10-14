<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\Support;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use MikhailMelnikov\JobsMonitor\Contracts\ActorResolver;

/**
 * Audit log of manual actions: who ran, retried or deleted what.
 *
 * There is no other history of manual runs, so every action is written
 * to the log together with the id of the user who performed it.
 */
final class ActionLogger
{
    /**
     * @param ActorResolver $actor Resolver of the current user
     */
    public function __construct(private readonly ActorResolver $actor)
    {
    }

    /**
     * Write an action to the audit log.
     *
     * @param string               $message Action description
     * @param array<string, mixed> $context Action details
     */
    public function log(string $message, array $context = []): void
    {
        /** @var string|null $channel */
        $channel = Config::get('jobs-monitor.log_channel');

        Log::channel($channel)->info('[jobs-monitor] ' . $message, $context + ['user_id' => $this->actor->id()]);
    }
}
