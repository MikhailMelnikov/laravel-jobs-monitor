<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\Tests\Feature;

use MikhailMelnikov\JobsMonitor\UseCase\DeleteFailedJobHandler;
use MikhailMelnikov\JobsMonitor\UseCase\DeleteQueuedJobHandler;
use MikhailMelnikov\JobsMonitor\UseCase\FlushFailedJobsHandler;
use MikhailMelnikov\JobsMonitor\UseCase\GetFailedJobsHandler;
use MikhailMelnikov\JobsMonitor\UseCase\GetQueuedJobsHandler;
use MikhailMelnikov\JobsMonitor\UseCase\GetQueueOverviewHandler;
use MikhailMelnikov\JobsMonitor\Tests\Fixtures\Recipient;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use MikhailMelnikov\JobsMonitor\Tests\TestCase;
use MikhailMelnikov\JobsMonitor\Tests\Fixtures\TestMailNotification;

/**
 * Тесты раздела "Очередь задач" в админке: сводка, список задач и действия над ними.
 */
class QueueJobsHandlersTest extends TestCase
{
    /**
     * Добавить задачу в таблицу очереди.
     *
     * @param string   $name       Класс job'а для payload'а
     * @param int|null $reservedAt Отметка о том, что задачу взял воркер
     * @param int      $delay      Задержка перед выполнением в секундах
     */
    private function pushJob(string $name, ?int $reservedAt = null, int $delay = 0): int
    {
        return (int) DB::table('jobs')->insertGetId([
            'queue' => 'default',
            'payload' => (string) json_encode([
                'displayName' => $name,
                'data' => ['commandName' => $name, 'command' => 'O:8:"stdClass":0:{}'],
            ]),
            'attempts' => 0,
            'reserved_at' => $reservedAt,
            'available_at' => time() + $delay,
            'created_at' => time(),
        ]);
    }

    /**
     * Добавить запись об упавшей задаче.
     *
     * @param string $uuid      UUID упавшей задачи
     * @param string $exception Текст исключения
     */
    private function pushFailedJob(string $uuid, string $exception): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => (string) json_encode(['uuid' => $uuid, 'displayName' => 'App\Jobs\BrokenJob']),
            'exception' => $exception,
            'failed_at' => now(),
        ]);
    }

    /**
     * Добавить запись об упавшей отправке уведомления.
     *
     * Payload собирается настоящей сериализацией job'а очереди: получатель уходит в него
     * ссылкой на модель, и именно эту ссылку разбирает список упавших задач.
     *
     * @param string $uuid UUID упавшей задачи
     * @param Recipient $user Получатель уведомления
     */
    private function pushFailedNotification(string $uuid, Recipient $user): void
    {
        $job = new SendQueuedNotifications($user, new TestMailNotification(), ['mail']);

        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'mail',
            'payload' => (string) json_encode([
                'uuid' => $uuid,
                'displayName' => TestMailNotification::class,
                'data' => ['commandName' => SendQueuedNotifications::class, 'command' => serialize($job)],
            ]),
            'exception' => 'Symfony\Component\Mailer\Exception\UnexpectedResponseException: '
                . 'Expected response code "250/251/252" but got code "501"',
            'failed_at' => now(),
        ]);
    }

    /**
     * Сводка разделяет задачи по состояниям: ждут воркера, выполняются, отложены, упали.
     */
    public function testOverviewCountsJobsByState(): void
    {
        $this->pushJob('App\Jobs\WaitingJob');
        $this->pushJob('App\Jobs\ReservedJob', reservedAt: time());
        $this->pushJob('App\Jobs\DelayedJob', delay: 3600);
        $this->pushFailedJob('11111111-1111-1111-1111-111111111111', 'Error: сломалось');

        $overview = (new GetQueueOverviewHandler())->handle();

        self::assertSame(1, $overview['waiting']);
        self::assertSame(1, $overview['reserved']);
        self::assertSame(1, $overview['delayed']);
        self::assertSame(1, $overview['failed']);
        self::assertSame(['default' => 3], $overview['queues']);
    }

    /**
     * Список задач очереди показывает класс job'а и отмечает те, что уже в работе.
     */
    public function testQueuedJobsListShowsJobNameAndReservedFlag(): void
    {
        $this->pushJob('App\Jobs\WaitingJob');
        $this->pushJob('App\Jobs\ReservedJob', reservedAt: time());

        $jobs = (new GetQueuedJobsHandler())->handle()->items();

        self::assertCount(2, $jobs);
        self::assertSame('App\Jobs\WaitingJob', $jobs[0]->name);
        self::assertFalse($jobs[0]->isReserved);
        self::assertTrue($jobs[1]->isReserved);
        self::assertNotNull($jobs[1]->reservedAt);
        self::assertStringNotContainsString('O:8:', $jobs[0]->payload);
    }

    /**
     * Задачу, ожидающую воркера, можно удалить из очереди.
     */
    public function testDeletesWaitingJob(): void
    {
        $id = $this->pushJob('App\Jobs\WaitingJob');

        (new DeleteQueuedJobHandler($id))->handle();

        self::assertDatabaseMissing('jobs', ['id' => $id]);
    }

    /**
     * Задачу, которую уже выполняет воркер, удалять нельзя: иначе результат её работы непредсказуем.
     */
    public function testRefusesToDeleteReservedJob(): void
    {
        $id = $this->pushJob('App\Jobs\ReservedJob', reservedAt: time());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('уже выполняется воркером');

        try {
            (new DeleteQueuedJobHandler($id))->handle();
        } finally {
            self::assertDatabaseHas('jobs', ['id' => $id]);
        }
    }

    /**
     * Удаление уже исчезнувшей задачи сообщает об этом, а не притворяется успехом.
     */
    public function testFailsWhenQueuedJobIsGone(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Задача не найдена');

        (new DeleteQueuedJobHandler(999999))->handle();
    }

    /**
     * Список упавших задач показывает первую строку ошибки отдельно от полного стектрейса.
     */
    public function testFailedJobsListSplitsMessageAndTrace(): void
    {
        $this->pushFailedJob(
            '22222222-2222-2222-2222-222222222222',
            "Error: деление на ноль\n#0 /app/somewhere.php(10): handle()"
        );

        $failed = (new GetFailedJobsHandler())->handle()->items();

        self::assertCount(1, $failed);
        self::assertSame('App\Jobs\BrokenJob', $failed[0]->name);
        self::assertSame('Error: деление на ноль', $failed[0]->message);
        self::assertStringContainsString('#0 /app/somewhere.php', $failed[0]->exception);
    }

    /**
     * У упавшего письма виден адрес получателя.
     *
     * Без него разобрать падение отправки нельзя: в стектрейсе SMTP-ошибки адреса нет,
     * а payload хранит получателя ссылкой на модель.
     */
    public function testFailedNotificationShowsRecipientEmail(): void
    {
        $user = Recipient::query()->create(['email' => 'recipient@example.com']);
        $uuid = '44444444-4444-4444-4444-444444444444';

        $this->pushFailedNotification($uuid, $user);

        $failed = (new GetFailedJobsHandler())->handle()->items();

        self::assertSame('recipient@example.com', $failed[0]->recipient);
    }

    /**
     * У задачи, не связанной с отправкой, получателя нет — и это не мешает показать её в списке.
     */
    public function testFailedJobWithoutRecipientIsStillListed(): void
    {
        $this->pushFailedJob('55555555-5555-5555-5555-555555555555', 'Error: сломалось');

        $failed = (new GetFailedJobsHandler())->handle()->items();

        self::assertCount(1, $failed);
        self::assertNull($failed[0]->recipient);
    }

    /**
     * Запись об упавшей задаче удаляется по UUID, а несуществующая — сообщает об ошибке.
     */
    public function testDeletesFailedJobByUuid(): void
    {
        $uuid = '33333333-3333-3333-3333-333333333333';
        $this->pushFailedJob($uuid, 'Error: сломалось');
        $provider = $this->app->make(FailedJobProviderInterface::class);

        (new DeleteFailedJobHandler($uuid, $provider))->handle();

        self::assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);

        $this->expectException(RuntimeException::class);
        (new DeleteFailedJobHandler($uuid, $provider))->handle();
    }

    /**
     * Очистка списка удаляет все записи об упавших задачах.
     */
    public function testFlushesAllFailedJobs(): void
    {
        $this->pushFailedJob('44444444-4444-4444-4444-444444444444', 'Error: сломалось');
        $this->pushFailedJob('55555555-5555-5555-5555-555555555555', 'Error: сломалось');

        (new FlushFailedJobsHandler($this->app->make(FailedJobProviderInterface::class)))->handle();

        self::assertSame(0, DB::table('failed_jobs')->count());
    }
}
