<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\UseCase;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Lang;
use MikhailMelnikov\JobsMonitor\Exceptions\JobsMonitorException;
use MikhailMelnikov\JobsMonitor\Support\ActionLogger;
use MikhailMelnikov\JobsMonitor\Support\QueueTables;

/**
 * Delete a job waiting in the queue.
 *
 * Needed when a wrong or stuck job got into the queue and there is no point
 * in waiting for a worker to run it.
 */
class DeleteQueuedJobHandler
{
    /**
     * Queue tables.
     */
    protected readonly QueueTables $tables;

    /**
     * Audit log of manual actions.
     */
    protected readonly ActionLogger $logger;

    /**
     * @param int               $id     Record id in the jobs table
     * @param QueueTables|null  $tables Queue tables; null — resolved from the container
     * @param ActionLogger|null $logger Audit log; null — resolved from the container
     */
    public function __construct(
        protected readonly int $id,
        ?QueueTables $tables = null,
        ?ActionLogger $logger = null,
    ) {
        $this->tables = $tables ?? Container::getInstance()->make(QueueTables::class);
        $this->logger = $logger ?? Container::getInstance()->make(ActionLogger::class);
    }

    /**
     * Remove the job from the queue.
     *
     * @throws JobsMonitorException The job is gone or already reserved by a worker
     */
    public function handle(): string
    {
        $job = $this->tables->jobs()->where('id', $this->id)->first();

        if ($job === null) {
            throw new JobsMonitorException((string) Lang::get('jobs-monitor::messages.queued_job_not_found'));
        }

        // the reserved_at condition guards against a worker taking the job between the two queries
        $deleted = $this->tables->jobs()->where('id', $this->id)->whereNull('reserved_at')->delete();

        if ($deleted === 0) {
            throw new JobsMonitorException((string) Lang::get('jobs-monitor::messages.queued_job_reserved'));
        }

        $this->logger->log('Queued job deleted', ['job_id' => $this->id]);

        return (string) Lang::get('jobs-monitor::messages.queued_job_deleted');
    }
}
