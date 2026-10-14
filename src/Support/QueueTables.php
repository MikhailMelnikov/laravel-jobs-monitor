<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\Support;

use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Config;

/**
 * Access to the database queue tables (jobs and failed_jobs).
 *
 * Table names and database connections are taken from config/queue.php,
 * so applications with renamed tables or a separate queue database work
 * without extra configuration.
 */
final class QueueTables
{
    /**
     * @param ConnectionResolverInterface $db Database connection resolver
     */
    public function __construct(private readonly ConnectionResolverInterface $db)
    {
    }

    /**
     * Query builder for the table of pending jobs.
     */
    public function jobs(): Builder
    {
        $connection = $this->queueConnection();

        /** @var string|null $database */
        $database = Config::get("queue.connections.{$connection}.connection");
        /** @var string $table */
        $table = Config::get("queue.connections.{$connection}.table", 'jobs');

        return $this->db->connection($database)->table($table);
    }

    /**
     * Query builder for the table of failed jobs.
     */
    public function failedJobs(): Builder
    {
        /** @var string|null $database */
        $database = Config::get('queue.failed.database');
        /** @var string $table */
        $table = Config::get('queue.failed.table', 'failed_jobs');

        return $this->db->connection($database)->table($table);
    }

    /**
     * Name of the monitored queue connection.
     *
     * The explicitly configured one wins; otherwise the default connection when
     * it uses the database driver, and the `database` connection as a fallback.
     */
    public function queueConnection(): string
    {
        /** @var string|null $configured */
        $configured = Config::get('jobs-monitor.queue_connection');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $default = (string) Config::get('queue.default');

        return Config::get("queue.connections.{$default}.driver") === 'database' ? $default : 'database';
    }
}
