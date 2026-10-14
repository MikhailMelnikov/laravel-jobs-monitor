<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\Dto;

use Carbon\CarbonInterface;

/**
 * A scheduler task prepared for display — the equivalent of `php artisan schedule:list`.
 */
final readonly class ScheduledTaskDto
{
    /**
     * @param string               $id                 Task identifier used in links and run buttons
     * @param string               $name               Task name (from ->name() or the job class)
     * @param string               $target             What is executed: a job class or an artisan command
     * @param string               $expression         Cron expression
     * @param string               $schedule           Human-readable schedule
     * @param CarbonInterface|null $nextRunAt          Next due date
     * @param string               $timezone           Schedule timezone
     * @param bool                 $withoutOverlapping The task never starts on top of an unfinished run
     * @param bool                 $onOneServer        The task runs on a single server only
     * @param bool                 $isRunning          Running right now (by the withoutOverlapping mutex)
     * @param bool                 $isJob              The task dispatches a job, so it can be pushed to the queue
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $target,
        public string $expression,
        public string $schedule,
        public ?CarbonInterface $nextRunAt,
        public string $timezone,
        public bool $withoutOverlapping,
        public bool $onOneServer,
        public bool $isRunning,
        public bool $isJob,
    ) {
    }
}
