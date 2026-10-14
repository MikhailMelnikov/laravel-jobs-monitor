<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\UseCase;

use Illuminate\Container\Container;
use MikhailMelnikov\JobsMonitor\Support\QueueTables;

/**
 * Queue state summary for the page header.
 */
final class GetQueueOverviewHandler
{
    /**
     * Queue tables.
     */
    private readonly QueueTables $tables;

    /**
     * @param QueueTables|null $tables Queue tables; null — resolved from the container
     */
    public function __construct(?QueueTables $tables = null)
    {
        $this->tables = $tables ?? Container::getInstance()->make(QueueTables::class);
    }

    /**
     * Count the queue jobs by state.
     *
     * @return array{connection: string, waiting: int, reserved: int, delayed: int, failed: int, queues: array<string, int>}
     */
    public function handle(): array
    {
        $now = time();

        return [
            'connection' => $this->tables->queueConnection(),
            'waiting' => $this->tables->jobs()->whereNull('reserved_at')->where('available_at', '<=', $now)->count(),
            'reserved' => $this->tables->jobs()->whereNotNull('reserved_at')->count(),
            'delayed' => $this->tables->jobs()->whereNull('reserved_at')->where('available_at', '>', $now)->count(),
            'failed' => (new CountFailedJobsHandler($this->tables))->handle(),
            'queues' => $this->countByQueue(),
        ];
    }

    /**
     * Number of jobs per queue.
     *
     * @return array<string, int> Queue name => number of jobs
     */
    private function countByQueue(): array
    {
        return $this->tables->jobs()
            ->selectRaw('queue, count(*) as total')
            ->groupBy('queue')
            ->orderBy('queue')
            ->pluck('total', 'queue')
            ->map(static fn (int|string $total): int => (int) $total)
            ->all();
    }
}
