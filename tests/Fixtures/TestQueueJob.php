<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job for the manual run tests.
 *
 * Execution is recorded with a static counter: it shows whether the job ran in
 * the current process or went to the queue.
 */
class TestQueueJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * How many times the job ran in the current process.
     */
    public static int $handledTimes = 0;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        self::$handledTimes++;
    }
}
