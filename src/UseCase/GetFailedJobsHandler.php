<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\UseCase;

use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use MikhailMelnikov\JobsMonitor\Dto\FailedJobDto;
use MikhailMelnikov\JobsMonitor\Support\JobPayloadParser;
use MikhailMelnikov\JobsMonitor\Support\QueueTables;
use stdClass;

/**
 * Failed queue jobs — the equivalent of `php artisan queue:failed`.
 */
class GetFailedJobsHandler
{
    /**
     * Queue tables.
     */
    protected readonly QueueTables $tables;

    /**
     * @param int|null         $perPage  Jobs per page; null — `jobs-monitor.per_page`
     * @param string           $pageName Query string parameter of the page number
     * @param QueueTables|null $tables   Queue tables; null — resolved from the container
     * @param int|null         $page     Page number; null — read from the query string by $pageName
     */
    public function __construct(
        protected readonly ?int $perPage = null,
        protected readonly string $pageName = 'failed_page',
        ?QueueTables $tables = null,
        protected readonly ?int $page = null,
    ) {
        $this->tables = $tables ?? Container::getInstance()->make(QueueTables::class);
    }

    /**
     * Get a page of failed jobs, the most recent failures first.
     *
     * @return LengthAwarePaginator<int, FailedJobDto>
     */
    public function handle(): LengthAwarePaginator
    {
        /** @var Paginator<int, stdClass> $paginator */
        $paginator = $this->tables->failedJobs()
            ->orderByDesc('id')
            ->paginate(perPage: $this->perPage ?? (int) Config::get('jobs-monitor.per_page', 25), pageName: $this->pageName, page: $this->page);

        $rows = $paginator->getCollection();

        /** @var Collection<int, JobPayloadParser> $parsers */
        $parsers = $rows->map(static fn (stdClass $row): JobPayloadParser => new JobPayloadParser((string) $row->payload));

        // addresses are resolved for the whole page at once: one by one would be a query per row
        $recipients = (new ResolveJobRecipientsHandler(
            $parsers->map(static fn (JobPayloadParser $parser): ?array => $parser->recipientReference())->all()
        ))->handle();

        $paginator->setCollection(
            $rows->map(fn (stdClass $row, int $key): FailedJobDto => $this->makeDto(
                $row,
                $parsers[$key],
                $recipients[$key] ?? null
            ))
        );

        return $paginator;
    }

    /**
     * Convert a failed_jobs row into a DTO.
     *
     * @param stdClass         $row       failed_jobs row
     * @param JobPayloadParser $parser    Parsed payload of the row
     * @param string|null      $recipient Recipient address when the job sends a notification
     */
    protected function makeDto(stdClass $row, JobPayloadParser $parser, ?string $recipient): FailedJobDto
    {
        $exception = (string) $row->exception;

        return new FailedJobDto(
            uuid: (string) $row->uuid,
            connection: (string) $row->connection,
            queue: (string) $row->queue,
            name: $parser->name(),
            failedAt: Carbon::parse($row->failed_at),
            message: $this->firstLine($exception),
            exception: $exception,
            payload: $parser->pretty(),
            recipient: $recipient,
        );
    }

    /**
     * First line of the stack trace — a short error description for the list.
     *
     * @param string $exception Full stack trace
     */
    protected function firstLine(string $exception): string
    {
        $firstLine = strtok($exception, "\n");

        return trim($firstLine === false ? $exception : $firstLine);
    }
}
