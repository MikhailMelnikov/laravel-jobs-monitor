<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\Tests\Unit;

use MikhailMelnikov\JobsMonitor\Support\ScheduledTaskLocator;
use MikhailMelnikov\JobsMonitor\UseCase\GetScheduledTasksHandler;
use Illuminate\Console\Scheduling\Schedule;
use MikhailMelnikov\JobsMonitor\Tests\TestCase;
use MikhailMelnikov\JobsMonitor\Tests\Fixtures\TestQueueJob;

/**
 * Тесты доступа к задачам планировщика: идентификаторы, распознавание job'ов и сборка списка.
 */
class ScheduledTaskLocatorTest extends TestCase
{
    /**
     * Планировщик с зарегистрированными для теста задачами.
     */
    private Schedule $schedule;

    /**
     * Зарегистрировать чистый планировщик, чтобы тесты не зависели от routes/console.php.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->schedule = new Schedule();
        $this->schedule->job(new TestQueueJob())->dailyAt('09:00')->name('Тестовая задача');
        $this->schedule->command('inspire')->hourly();
    }

    /**
     * Идентификатор задачи не зависит от порядка регистрации и повторяется между вызовами.
     */
    public function testIdIsStableBetweenCalls(): void
    {
        $locator = new ScheduledTaskLocator($this->schedule);
        [$job, $command] = $locator->all();

        self::assertSame($locator->makeId($job), $locator->makeId($job));
        self::assertNotSame($locator->makeId($job), $locator->makeId($command));
    }

    /**
     * По идентификатору находится та же самая задача, а по чужому — ничего.
     */
    public function testFindsTaskById(): void
    {
        $locator = new ScheduledTaskLocator($this->schedule);
        $event = $locator->all()[0];

        self::assertSame($event, $locator->findById($locator->makeId($event)));
        self::assertNull($locator->findById(md5('несуществующая задача')));
    }

    /**
     * Для задачи-job'а определяется его класс, для artisan-команды — сама команда.
     */
    public function testResolvesTargetForJobsAndCommands(): void
    {
        $locator = new ScheduledTaskLocator($this->schedule);
        [$job, $command] = $locator->all();

        self::assertSame(TestQueueJob::class, $locator->resolveTarget($job));
        self::assertTrue($locator->isJob($job));

        self::assertSame('inspire', $locator->resolveTarget($command));
        self::assertFalse($locator->isJob($command));
    }

    /**
     * Список задач для админки содержит расписание словами и время следующего запуска.
     */
    public function testBuildsTaskListForAdminPanel(): void
    {
        $tasks = (new GetScheduledTasksHandler(new ScheduledTaskLocator($this->schedule)))->handle();

        self::assertCount(2, $tasks);

        $task = $tasks[0];
        self::assertSame('Тестовая задача', $task->name);
        self::assertSame(TestQueueJob::class, $task->target);
        self::assertSame('0 9 * * *', $task->expression);
        self::assertSame('ежедневно в 09:00', $task->schedule);
        self::assertTrue($task->isJob);
        self::assertFalse($task->isRunning);
        self::assertNotNull($task->nextRunAt);
    }
}
