<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\UseCase;

use Illuminate\Container\Container;
use MikhailMelnikov\JobsMonitor\Support\QueueTables;

/**
 * Number of failed queue jobs — a counter for admin navigation.
 */
class CountFailedJobsHandler
{
    /**
     * Queue tables.
     */
    protected readonly QueueTables $tables;

    /**
     * @param QueueTables|null $tables Queue tables; null — resolved from the container
     */
    public function __construct(?QueueTables $tables = null)
    {
        $this->tables = $tables ?? Container::getInstance()->make(QueueTables::class);
    }

    /**
     * Count the failed job records.
     */
    public function handle(): int
    {
        return $this->tables->failedJobs()->count();
    }
}
