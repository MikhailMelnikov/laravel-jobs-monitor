<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\Dto;

use Carbon\CarbonInterface;

/**
 * A queue record (the jobs table) waiting for a worker or already reserved by one.
 */
final readonly class QueuedJobDto
{
    /**
     * @param int                  $id          Record id in the jobs table
     * @param string               $queue       Queue name
     * @param string               $name        Job class taken from the payload
     * @param int                  $attempts    How many times the job has been picked up
     * @param bool                 $isReserved  The job is being processed by a worker right now
     * @param CarbonInterface|null $reservedAt  When a worker reserved the job
     * @param CarbonInterface      $availableAt When the job becomes available to workers
     * @param CarbonInterface      $createdAt   When the job was pushed to the queue
     * @param string               $payload     Human-readable payload
     */
    public function __construct(
        public int $id,
        public string $queue,
        public string $name,
        public int $attempts,
        public bool $isReserved,
        public ?CarbonInterface $reservedAt,
        public CarbonInterface $availableAt,
        public CarbonInterface $createdAt,
        public string $payload,
    ) {
    }
}
