<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\Enum;

use Illuminate\Support\Facades\Lang;

/**
 * How a scheduled task is run manually.
 */
enum RunMode: string
{
    /** The task is pushed to the queue and executed by a worker. */
    case QUEUE = 'queue';

    /** The task is executed right inside the current request. */
    case SYNC = 'sync';

    /**
     * Mode label for the UI.
     */
    public function label(): string
    {
        return (string) Lang::get('jobs-monitor::run_mode.' . $this->value);
    }
}
