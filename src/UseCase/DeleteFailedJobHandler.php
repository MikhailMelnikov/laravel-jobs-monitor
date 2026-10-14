<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\UseCase;

use Illuminate\Container\Container;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Facades\Lang;
use MikhailMelnikov\JobsMonitor\Exceptions\JobsMonitorException;
use MikhailMelnikov\JobsMonitor\Support\ActionLogger;

/**
 * Delete a failed job record — the equivalent of `php artisan queue:forget`.
 */
final class DeleteFailedJobHandler
{
    /**
     * Failed job storage.
     */
    private readonly FailedJobProviderInterface $provider;

    /**
     * Audit log of manual actions.
     */
    private readonly ActionLogger $logger;

    /**
     * @param string                          $uuid     Failed job UUID
     * @param FailedJobProviderInterface|null $provider Failed job storage; null — resolved from the container
     * @param ActionLogger|null               $logger   Audit log; null — resolved from the container
     */
    public function __construct(
        private readonly string $uuid,
        ?FailedJobProviderInterface $provider = null,
        ?ActionLogger $logger = null,
    ) {
        $this->provider = $provider ?? Container::getInstance()->make(FailedJobProviderInterface::class);
        $this->logger   = $logger ?? Container::getInstance()->make(ActionLogger::class);
    }

    /**
     * Delete the failed job record.
     *
     * @throws JobsMonitorException No failed job with this UUID
     */
    public function handle(): string
    {
        if (! $this->provider->forget($this->uuid)) {
            throw new JobsMonitorException((string) Lang::get('jobs-monitor::messages.failed_job_not_found'));
        }

        $this->logger->log('Failed job deleted', ['uuid' => $this->uuid]);

        return (string) Lang::get('jobs-monitor::messages.failed_job_deleted');
    }
}
