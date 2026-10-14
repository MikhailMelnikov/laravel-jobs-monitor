<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\Dto;

use Carbon\CarbonInterface;

/**
 * A failed queue job (the failed_jobs table).
 */
final readonly class FailedJobDto
{
    /**
     * @param string          $uuid       Job UUID — identifier for queue:retry and queue:forget
     * @param string          $connection Queue connection the job failed on
     * @param string          $queue      Queue name
     * @param string          $name       Job class taken from the payload
     * @param CarbonInterface $failedAt   Time of failure
     * @param string          $message    First line of the exception
     * @param string          $exception  Full stack trace
     * @param string          $payload    Human-readable payload
     * @param string|null     $recipient  Recipient address when the job sends a notification
     */
    public function __construct(
        public string $uuid,
        public string $connection,
        public string $queue,
        public string $name,
        public CarbonInterface $failedAt,
        public string $message,
        public string $exception,
        public string $payload,
        public ?string $recipient = null,
    ) {
    }
}
