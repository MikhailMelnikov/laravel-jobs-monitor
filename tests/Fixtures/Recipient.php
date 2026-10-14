<?php

declare(strict_types=1);

namespace MikhailMelnikov\JobsMonitor\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

/**
 * Notification recipient model for the failed jobs tests.
 *
 * @property int         $id
 * @property string|null $email
 */
class Recipient extends Model
{
    use Notifiable;

    /**
     * The table has no timestamps.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Mass assignable attributes.
     *
     * @var string[]
     */
    protected $fillable = ['email'];
}
