<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\UseCase;

use Carbon\CarbonInterface;
use DateTimeZone;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Config;
use MikhailMelnikov\JobsMonitor\Dto\ScheduledTaskDto;
use MikhailMelnikov\JobsMonitor\Support\CronExpressionTranslator;
use MikhailMelnikov\JobsMonitor\Support\ScheduledTaskLocator;
use Throwable;

/**
 * List of scheduler tasks — the equivalent of `php artisan schedule:list`.
 */
final class GetScheduledTasksHandler
{
    /**
     * Access to the scheduler tasks.
     */
    private readonly ScheduledTaskLocator $locator;

    /**
     * @param ScheduledTaskLocator|null $locator Access to the scheduler tasks; null — resolved from the container
     */
    public function __construct(?ScheduledTaskLocator $locator = null)
    {
        $this->locator = $locator ?? Container::getInstance()->make(ScheduledTaskLocator::class);
    }

    /**
     * Collect the scheduler tasks in registration order.
     *
     * @return ScheduledTaskDto[]
     */
    public function handle(): array
    {
        return array_map(
            fn (Event $event): ScheduledTaskDto => $this->makeDto($event),
            array_values($this->locator->all())
        );
    }

    /**
     * Convert a scheduler task into a DTO.
     *
     * @param Event $event Scheduler task
     */
    private function makeDto(Event $event): ScheduledTaskDto
    {
        return new ScheduledTaskDto(
            id: $this->locator->makeId($event),
            name: $this->locator->resolveName($event),
            target: $this->locator->resolveTarget($event),
            expression: $event->expression,
            schedule: (new CronExpressionTranslator($event->expression))->handle(),
            nextRunAt: $this->resolveNextRunDate($event),
            timezone: $this->resolveTimezone($event),
            withoutOverlapping: $event->withoutOverlapping,
            onOneServer: $event->onOneServer,
            isRunning: $this->isRunning($event),
            isJob: $this->locator->isJob($event),
        );
    }

    /**
     * Next due date of the task.
     *
     * @param Event $event Scheduler task
     *
     * @return CarbonInterface|null null when the expression cannot be parsed
     */
    private function resolveNextRunDate(Event $event): ?CarbonInterface
    {
        try {
            return $event->nextRunDate();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Timezone of the task schedule.
     *
     * The scheduler keeps it either as a string or as a DateTimeZone object.
     *
     * @param Event $event Scheduler task
     */
    private function resolveTimezone(Event $event): string
    {
        $timezone = $event->timezone ?? Config::get('app.timezone');

        return $timezone instanceof DateTimeZone ? $timezone->getName() : (string) $timezone;
    }

    /**
     * Whether the task is running right now.
     *
     * Detected by the withoutOverlapping lock: while the task runs, the scheduler
     * holds its mutex. Without that option the flag is unavailable and always false.
     *
     * @param Event $event Scheduler task
     */
    private function isRunning(Event $event): bool
    {
        if (! $event->withoutOverlapping) {
            return false;
        }

        try {
            return $event->mutex->exists($event);
        } catch (Throwable) {
            return false;
        }
    }
}
