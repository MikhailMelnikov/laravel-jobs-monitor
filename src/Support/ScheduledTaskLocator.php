<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\Support;

use Closure;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use ReflectionFunction;
use ReflectionObject;
use Throwable;

/**
 * Access to the tasks registered in the application scheduler.
 *
 * The scheduler neither stores tasks in the database nor gives them ids, so the
 * class assigns every event a stable identifier — the UI uses it to find a task
 * for a manual run.
 */
final class ScheduledTaskLocator
{
    /**
     * @param Schedule $schedule Application scheduler with all registered tasks
     */
    public function __construct(private readonly Schedule $schedule)
    {
    }

    /**
     * All scheduler tasks.
     *
     * @return Event[]
     */
    public function all(): array
    {
        return $this->schedule->events();
    }

    /**
     * Find a task by its identifier.
     *
     * @param string $id Identifier from {@see self::makeId()}
     *
     * @return Event|null null when no task with this identifier is registered anymore
     */
    public function findById(string $id): ?Event
    {
        foreach ($this->all() as $event) {
            if ($this->makeId($event) === $id) {
                return $event;
            }
        }

        return null;
    }

    /**
     * Build a task identifier.
     *
     * It is derived from the schedule and the task name: it does not depend on
     * the registration order and does not change between requests.
     *
     * @param Event $event Scheduler task
     */
    public function makeId(Event $event): string
    {
        return md5($event->expression . '|' . ($event->description ?? '') . '|' . ($event->command ?? ''));
    }

    /**
     * Task display name: the name from ->name() or what the task executes.
     *
     * @param Event $event Scheduler task
     */
    public function resolveName(Event $event): string
    {
        return $event->description === null || $event->description === ''
            ? $this->resolveTarget($event)
            : $event->description;
    }

    /**
     * What the task executes: a job class or an artisan command.
     *
     * @param Event $event Scheduler task
     */
    public function resolveTarget(Event $event): string
    {
        if ($event->command !== null) {
            return $this->cleanCommand($event->command);
        }

        return $this->resolveJobClass($event) ?? 'Closure';
    }

    /**
     * Whether the task dispatches a job — decides if it can be pushed to the queue.
     *
     * @param Event $event Scheduler task
     */
    public function isJob(Event $event): bool
    {
        return $event instanceof CallbackEvent && $this->resolveJobClass($event) !== null;
    }

    /**
     * Extract the job class from the closure created by Schedule::job().
     *
     * The closure keeps the job in a captured variable with no public accessor,
     * so the class is read via reflection. It is used for display only: on any
     * error null is returned and the task is shown without a class.
     *
     * @param Event $event Scheduler task
     *
     * @return string|null null when the task is not a job
     */
    private function resolveJobClass(Event $event): ?string
    {
        if (! $event instanceof CallbackEvent) {
            return null;
        }

        try {
            $property = (new ReflectionObject($event))->getProperty('callback');
            $callback = $property->getValue($event);

            if (! $callback instanceof Closure) {
                return null;
            }

            $job = (new ReflectionFunction($callback))->getClosureUsedVariables()['job'] ?? null;

            return match (true) {
                is_object($job) => $job::class,
                is_string($job) => $job,
                default => null,
            };
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Strip the PHP binary and artisan path from a scheduler command.
     *
     * @param string $command Command as stored by the scheduler
     */
    private function cleanCommand(string $command): string
    {
        $cleaned = preg_replace('~^.*?artisan.\s+~', '', $command) ?? $command;

        return trim(str_replace("'", '', $cleaned));
    }
}
