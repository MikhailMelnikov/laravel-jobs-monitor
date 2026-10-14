<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use MikhailMelnikov\JobsMonitor\Contracts\ActorResolver;
use MikhailMelnikov\JobsMonitor\Enum\RunMode;
use MikhailMelnikov\JobsMonitor\Support\CronExpressionTranslator;
use MikhailMelnikov\JobsMonitor\Support\GuardActorResolver;
use MikhailMelnikov\JobsMonitor\Support\QueueTables;
use MikhailMelnikov\JobsMonitor\Tests\Fixtures\TestActorResolver;
use MikhailMelnikov\JobsMonitor\Tests\TestCase;
use MikhailMelnikov\JobsMonitor\UseCase\DeleteQueuedJobHandler;
use MikhailMelnikov\JobsMonitor\UseCase\GetQueueOverviewHandler;

/**
 * Package configuration: locales, queue tables and the actor resolver contract.
 */
class ConfigurationTest extends TestCase
{
    /**
     * English is shipped alongside Russian.
     */
    public function testTranslatesIntoEnglish(): void
    {
        $this->app->setLocale('en');

        self::assertSame('daily at 09:00', (new CronExpressionTranslator('0 9 * * *'))->handle());
        self::assertSame('hourly at :15', (new CronExpressionTranslator('15 * * * *'))->handle());
        self::assertSame('weekly on Monday at 00:00', (new CronExpressionTranslator('0 0 * * 1'))->handle());
        self::assertSame('Run now', RunMode::SYNC->label());
    }

    /**
     * Table names are taken from config/queue.php instead of being hardcoded.
     */
    public function testReadsTableNamesFromQueueConfig(): void
    {
        Schema::create('custom_jobs', static function (Blueprint $table): void {
            $table->id();
            $table->string('queue');
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
        config(['queue.connections.database.table' => 'custom_jobs']);

        DB::table('custom_jobs')->insert([
            'queue' => 'emails',
            'payload' => '{}',
            'attempts' => 0,
            'available_at' => time(),
            'created_at' => time(),
        ]);

        self::assertSame(['emails' => 1], (new GetQueueOverviewHandler())->handle()['queues']);
    }

    /**
     * A non-database default queue falls back to the `database` connection; an explicit setting wins.
     */
    public function testResolvesMonitoredQueueConnection(): void
    {
        $tables = $this->app->make(QueueTables::class);

        config(['queue.default' => 'redis', 'queue.connections.redis' => ['driver' => 'redis']]);
        self::assertSame('database', $tables->queueConnection());

        config(['jobs-monitor.queue_connection' => 'other']);
        self::assertSame('other', $tables->queueConnection());
    }

    /**
     * The default resolver reads the auth guard; an admin integration may bind its own.
     */
    public function testActorResolverIsReplaceable(): void
    {
        self::assertInstanceOf(GuardActorResolver::class, $this->app->make(ActorResolver::class));

        $this->app->bind(ActorResolver::class, TestActorResolver::class);
        $id = (int) DB::table('jobs')->insertGetId([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'available_at' => time(),
            'created_at' => time(),
        ]);

        Log::shouldReceive('channel')->once()->andReturnSelf();
        Log::shouldReceive('info')->once()->withArgs(
            static fn (string $message, array $context): bool => $context['user_id'] === 42 && $context['job_id'] === $id
        );

        (new DeleteQueuedJobHandler($id))->handle();
    }
}
