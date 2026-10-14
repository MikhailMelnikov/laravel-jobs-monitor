<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\Exceptions;

use RuntimeException;

/**
 * An expected refusal (the job is gone, the task is already running, …).
 *
 * The message is translated and safe to show to the user as a warning,
 * unlike arbitrary exceptions thrown by the task itself.
 */
class JobsMonitorException extends RuntimeException
{
}
