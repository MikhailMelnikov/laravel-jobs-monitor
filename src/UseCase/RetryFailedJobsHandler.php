<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\UseCase;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Lang;
use MikhailMelnikov\JobsMonitor\Support\ActionLogger;

/**
 * Retry failed jobs — the equivalent of `php artisan queue:retry`.
 *
 * The jobs are pushed back to the queue and will be processed by a worker.
 */
final class RetryFailedJobsHandler
{
    /**
     * Audit log of manual actions.
     */
    private readonly ActionLogger $logger;

    /**
     * @param string[]          $uuids  Failed job UUIDs; empty array — retry all
     * @param ActionLogger|null $logger Audit log; null — resolved from the container
     */
    public function __construct(private readonly array $uuids = [], ?ActionLogger $logger = null)
    {
        $this->logger = $logger ?? Container::getInstance()->make(ActionLogger::class);
    }

    /**
     * Push the jobs back to the queue and get a result message.
     */
    public function handle(): string
    {
        $ids = $this->uuids === [] ? ['all'] : $this->uuids;

        Artisan::call('queue:retry', ['id' => $ids]);

        $this->logger->log('Failed jobs retried', ['jobs' => $ids]);

        return (string) Lang::get(
            $this->uuids === [] ? 'jobs-monitor::messages.failed_jobs_retried' : 'jobs-monitor::messages.failed_job_retried'
        );
    }
}
