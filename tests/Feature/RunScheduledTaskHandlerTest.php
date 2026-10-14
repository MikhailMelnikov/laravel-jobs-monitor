<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\Tests\Feature;

use MikhailMelnikov\JobsMonitor\Enum\RunMode;
use MikhailMelnikov\JobsMonitor\Support\ScheduledTaskLocator;
use MikhailMelnikov\JobsMonitor\UseCase\RunScheduledTaskHandler;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use MikhailMelnikov\JobsMonitor\Tests\TestCase;
use MikhailMelnikov\JobsMonitor\Tests\Fixtures\TestQueueJob;

/**
 * Тесты ручного запуска задачи планировщика из админки.
 */
class RunScheduledTaskHandlerTest extends TestCase
{
    /**
     * Планировщик с одной тестовой задачей.
     */
    private Schedule $schedule;

    /**
     * Доступ к задачам тестового планировщика.
     */
    private ScheduledTaskLocator $locator;

    /**
     * Подготовить планировщик и обнулить счётчик выполнений тестового job'а.
     */
    protected function setUp(): void
    {
        parent::setUp();

        TestQueueJob::$handledTimes = 0;

        $this->schedule = new Schedule();
        $this->locator = new ScheduledTaskLocator($this->schedule);
    }

    /**
     * Зарегистрировать тестовую задачу и получить её идентификатор.
     *
     * @param bool $withoutOverlapping Запретить ли наложение запусков
     */
    private function registerTask(bool $withoutOverlapping = false): string
    {
        $event = $this->schedule->job(new TestQueueJob())->everyMinute()->name('Тестовая задача');

        if ($withoutOverlapping) {
            $event->withoutOverlapping();
        }

        return $this->locator->makeId($event);
    }

    /**
     * В режиме "в очередь" задача уходит воркеру, а не выполняется в запросе администратора.
     */
    public function testQueueModeDispatchesJobToQueue(): void
    {
        Queue::fake();

        $message = (new RunScheduledTaskHandler(
            $this->registerTask(),
            RunMode::QUEUE,
            $this->locator,
            $this->app
        ))->handle();

        Queue::assertPushed(TestQueueJob::class);
        self::assertSame(0, TestQueueJob::$handledTimes);
        self::assertStringContainsString('отправлена в очередь', $message);
    }

    /**
     * В режиме "сейчас" задача выполняется в текущем процессе, а подключение очереди восстанавливается.
     */
    public function testSyncModeExecutesJobImmediately(): void
    {
        config(['queue.default' => 'database']);

        $message = (new RunScheduledTaskHandler(
            $this->registerTask(),
            RunMode::SYNC,
            $this->locator,
            $this->app
        ))->handle();

        self::assertSame(1, TestQueueJob::$handledTimes);
        self::assertSame('database', config('queue.default'));
        self::assertStringContainsString('выполнена', $message);
    }

    /**
     * Запуск несуществующей задачи не приводит к тихому бездействию.
     */
    public function testFailsWhenTaskIsNotRegistered(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Задача не найдена в планировщике.');

        (new RunScheduledTaskHandler(md5('нет такой задачи'), RunMode::QUEUE, $this->locator, $this->app))->handle();
    }

    /**
     * Пока задача с запретом наложений выполняется, повторный запуск отклоняется с пояснением.
     */
    public function testRefusesToRunTaskThatIsAlreadyRunning(): void
    {
        $taskId = $this->registerTask(withoutOverlapping: true);
        $event = $this->locator->findById($taskId);
        self::assertNotNull($event);
        $event->mutex->create($event);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('уже выполняется');

            (new RunScheduledTaskHandler($taskId, RunMode::QUEUE, $this->locator, $this->app))->handle();
        } finally {
            $event->mutex->forget($event);
        }
    }
}
