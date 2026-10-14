<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\Tests\Fixtures;

use Illuminate\Notifications\Notification;

/**
 * Notification for the payload parsing tests.
 *
 * A named class is required: the job payload is serialized, and anonymous
 * classes cannot be serialized in PHP.
 */
class TestMailNotification extends Notification
{
    /**
     * Delivery channels.
     *
     * @param object $notifiable Notification recipient
     *
     * @return string[]
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }
}
