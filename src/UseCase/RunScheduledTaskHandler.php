<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\UseCase;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Container\Container as ContainerInstance;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;
use MikhailMelnikov\JobsMonitor\Enum\RunMode;
use MikhailMelnikov\JobsMonitor\Exceptions\JobsMonitorException;
use MikhailMelnikov\JobsMonitor\Support\ActionLogger;
use MikhailMelnikov\JobsMonitor\Support\ScheduledTaskLocator;
use Throwable;

/**
 * Run a scheduler task manually.
 *
 * The task is executed by the same code as on schedule: the scheduler event
 * itself decides whether to push a job to the queue or run an artisan command.
 */
class RunScheduledTaskHandler
{
    /**
     * Access to the scheduler tasks.
     */
    protected readonly ScheduledTaskLocator $locator;

    /**
     * Container the scheduler runs the task with.
     */
    protected readonly Container $container;

    /**
     * Audit log of manual actions.
     */
    protected readonly ActionLogger $logger;

    /**
     * @param string                    $taskId    Task identifier from {@see ScheduledTaskLocator::makeId()}
     * @param RunMode                   $mode      Run mode: push to the queue or run now
     * @param ScheduledTaskLocator|null $locator   Access to the scheduler tasks; null — resolved from the container
     * @param Container|null            $container Container the task runs with; null — the global container
     * @param ActionLogger|null         $logger    Audit log; null — resolved from the container
     */
    public function __construct(
        protected readonly string $taskId,
        protected readonly RunMode $mode,
        ?ScheduledTaskLocator $locator = null,
        ?Container $container = null,
        ?ActionLogger $logger = null,
    ) {
        $this->container = $container ?? ContainerInstance::getInstance();
        $this->locator   = $locator ?? $this->container->make(ScheduledTaskLocator::class);
        $this->logger    = $logger ?? $this->container->make(ActionLogger::class);
    }

    /**
     * Run the task and get a result message.
     *
     * @throws JobsMonitorException The task is not found or already running
     * @throws Throwable            The task itself failed in the synchronous mode
     */
    public function handle(): string
    {
        $event = $this->locator->findById($this->taskId);

        if ($event === null) {
            throw new JobsMonitorException((string) Lang::get('jobs-monitor::messages.task_not_found'));
        }

        $name = $this->locator->resolveName($event);

        if ($event->withoutOverlapping && $event->mutex->exists($event)) {
            throw new JobsMonitorException(
                (string) Lang::get('jobs-monitor::messages.task_already_running', ['name' => $name])
            );
        }

        $this->logger->log('Scheduled task run manually', ['task' => $name, 'mode' => $this->mode->value]);
        $this->run($event);

        return $this->mode === RunMode::SYNC
            ? (string) Lang::get('jobs-monitor::messages.task_executed', ['name' => $name])
            : (string) Lang::get('jobs-monitor::messages.task_queued', [
                'name' => $name,
                'queue' => (string) Config::get('queue.default'),
            ]);
    }

    /**
     * Execute the scheduler event in the chosen mode.
     *
     * The synchronous mode temporarily switches the queue connection to sync:
     * the job dispatched by the task runs in the current process and its error
     * reaches the user instead of settling in failed_jobs.
     *
     * @param Event $event Scheduler task
     *
     * @throws Throwable Task execution error
     */
    protected function run(Event $event): void
    {
        if ($this->mode === RunMode::QUEUE) {
            $event->run($this->container);

            return;
        }

        $connection = Config::get('queue.default');
        $timeLimit  = Config::get('jobs-monitor.sync_time_limit', 0);

        // the task runs inside an HTTP request and may take longer than the usual PHP limit
        if (is_int($timeLimit)) {
            set_time_limit($timeLimit);
        }

        Config::set('queue.default', 'sync');

        try {
            $event->run($this->container);
        } finally {
            Config::set('queue.default', $connection);
        }
    }
}
