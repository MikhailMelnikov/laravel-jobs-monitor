<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use MikhailMelnikov\JobsMonitor\JobsMonitorServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Base test case: the package on top of a bare Laravel app with an in-memory sqlite database.
 */
abstract class TestCase extends Orchestra
{
    /**
     * Package providers to register.
     *
     * @param \Illuminate\Foundation\Application $app Application
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [JobsMonitorServiceProvider::class];
    }

    /**
     * Configure the environment: sqlite, database queue and the Russian locale
     * (the package ships both, the tests check the translated messages).
     *
     * @param \Illuminate\Foundation\Application $app Application
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.locale', 'ru');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('queue.default', 'database');
        $app['config']->set('queue.connections.database', [
            'driver' => 'database',
            'connection' => null,
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
        ]);
        $app['config']->set('queue.failed', [
            'driver' => 'database-uuids',
            'database' => 'testing',
            'table' => 'failed_jobs',
        ]);
    }

    /**
     * Create the queue tables and the recipient fixture table.
     */
    protected function defineDatabaseMigrations(): void
    {
        Schema::create('jobs', static function (Blueprint $table): void {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('failed_jobs', static function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        Schema::create('recipients', static function (Blueprint $table): void {
            $table->id();
            $table->string('email')->nullable();
        });
    }
}
