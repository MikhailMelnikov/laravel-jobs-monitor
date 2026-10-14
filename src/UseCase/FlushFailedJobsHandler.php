<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\UseCase;

use Illuminate\Container\Container;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Facades\Lang;
use MikhailMelnikov\JobsMonitor\Support\ActionLogger;

/**
 * Delete all failed job records — the equivalent of `php artisan queue:flush`.
 */
final class FlushFailedJobsHandler
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
     * @param FailedJobProviderInterface|null $provider Failed job storage; null — resolved from the container
     * @param ActionLogger|null               $logger   Audit log; null — resolved from the container
     */
    public function __construct(?FailedJobProviderInterface $provider = null, ?ActionLogger $logger = null)
    {
        $this->provider = $provider ?? Container::getInstance()->make(FailedJobProviderInterface::class);
        $this->logger   = $logger ?? Container::getInstance()->make(ActionLogger::class);
    }

    /**
     * Delete all failed job records.
     */
    public function handle(): string
    {
        $this->provider->flush();

        $this->logger->log('Failed jobs flushed');

        return (string) Lang::get('jobs-monitor::messages.failed_jobs_flushed');
    }
}
