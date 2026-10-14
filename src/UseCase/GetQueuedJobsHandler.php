<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\UseCase;

use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\Config;
use MikhailMelnikov\JobsMonitor\Dto\QueuedJobDto;
use MikhailMelnikov\JobsMonitor\Support\JobPayloadParser;
use MikhailMelnikov\JobsMonitor\Support\QueueTables;
use stdClass;

/**
 * Jobs waiting for a queue worker (the jobs table).
 */
final class GetQueuedJobsHandler
{
    /**
     * Queue tables.
     */
    private readonly QueueTables $tables;

    /**
     * @param int|null         $perPage  Jobs per page; null — `jobs-monitor.per_page`
     * @param string           $pageName Query string parameter of the page number
     * @param QueueTables|null $tables   Queue tables; null — resolved from the container
     */
    public function __construct(
        private readonly ?int $perPage = null,
        private readonly string $pageName = 'queue_page',
        ?QueueTables $tables = null,
    ) {
        $this->tables = $tables ?? Container::getInstance()->make(QueueTables::class);
    }

    /**
     * Get a page of queued jobs, the oldest first.
     *
     * @return LengthAwarePaginator<int, QueuedJobDto>
     */
    public function handle(): LengthAwarePaginator
    {
        /** @var Paginator<int, stdClass> $paginator */
        $paginator = $this->tables->jobs()
            ->orderBy('id')
            ->paginate(perPage: $this->perPage ?? (int) Config::get('jobs-monitor.per_page', 25), pageName: $this->pageName);

        return $paginator->through(fn (stdClass $row): QueuedJobDto => $this->makeDto($row));
    }

    /**
     * Convert a jobs row into a DTO.
     *
     * @param stdClass $row jobs row
     */
    private function makeDto(stdClass $row): QueuedJobDto
    {
        $parser = new JobPayloadParser((string) $row->payload);

        return new QueuedJobDto(
            id: (int) $row->id,
            queue: (string) $row->queue,
            name: $parser->name(),
            attempts: (int) $row->attempts,
            isReserved: $row->reserved_at !== null,
            reservedAt: $row->reserved_at !== null ? Carbon::createFromTimestamp((int) $row->reserved_at) : null,
            availableAt: Carbon::createFromTimestamp((int) $row->available_at),
            createdAt: Carbon::createFromTimestamp((int) $row->created_at),
            payload: $parser->pretty(),
        );
    }
}
