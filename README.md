# Laravel Jobs Monitor

Admin-agnostic core for looking after the Laravel **scheduler** and the **database queue** without a console:

- scheduler tasks with a human-readable schedule, next run, "running now" flag — like `schedule:list`;
- manual run of a task: push its job to the queue or execute it right now;
- queue overview (waiting / running / delayed / failed, per queue) and the list of queued jobs;
- failed jobs with the error, stack trace, payload and the **notification recipient's address**;
- retry, forget, flush — like `queue:retry`, `queue:forget`, `queue:flush`;
- audit log of every manual action with the id of the user who did it;
- English and Russian translations.

The package contains no UI. Use an admin panel integration:

| Admin panel | Package |
|---|---|
| Backpack for Laravel | [`mikhailmelnikov/backpack-jobs-monitor`](https://github.com/MikhailMelnikov/backpack-jobs-monitor) |
| Filament | planned |

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- the `database` queue driver for the queue pages (the scheduler pages work with any driver)

## Installation

```bash
composer require mikhailmelnikov/laravel-jobs-monitor
```

Optionally publish the config and translations:

```bash
php artisan vendor:publish --tag=jobs-monitor-config
php artisan vendor:publish --tag=jobs-monitor-lang
```

## Configuration

`config/jobs-monitor.php`:

| Key | Default | Meaning |
|---|---|---|
| `queue_connection` | `null` | Queue connection whose tables are shown. `null` — `queue.default` if it uses the database driver, otherwise `database`. Table names and the DB connection are read from `config/queue.php`. |
| `per_page` | `25` | Page size of the queued and failed job lists. |
| `recipients.enabled` | `true` | Resolve the recipient address of failed notifications. |
| `recipients.attribute` | `email` | Model attribute holding the address. |
| `sync_time_limit` | `0` | `set_time_limit()` for "run now"; `null` leaves it untouched. |
| `log_channel` | `null` | Channel of the audit log. |
| `actor_guard` | `null` | Guard of the default `ActorResolver`. |

## Usage (for integrations)

Every use case is a class with constructor arguments and a `handle()` method. Dependencies are optional and resolved from the container.

```php
use MikhailMelnikov\JobsMonitor\Enum\RunMode;
use MikhailMelnikov\JobsMonitor\Exceptions\JobsMonitorException;
use MikhailMelnikov\JobsMonitor\UseCase;

$tasks    = (new UseCase\GetScheduledTasksHandler())->handle();   // ScheduledTaskDto[]
$overview = (new UseCase\GetQueueOverviewHandler())->handle();    // counters
$queued   = (new UseCase\GetQueuedJobsHandler())->handle();       // paginator of QueuedJobDto
$failed   = (new UseCase\GetFailedJobsHandler())->handle();       // paginator of FailedJobDto

try {
    $message = (new UseCase\RunScheduledTaskHandler($taskId, RunMode::QUEUE))->handle();
    // also: DeleteQueuedJobHandler($id), RetryFailedJobsHandler([$uuid]), RetryFailedJobsHandler(),
    //       DeleteFailedJobHandler($uuid), FlushFailedJobsHandler()
} catch (JobsMonitorException $e) {
    // expected refusal (gone, already running, …): translated, safe to show as a warning
}
```

Action handlers return a translated success message.

### Who performed the action

Manual actions are logged with the current user id resolved through `MikhailMelnikov\JobsMonitor\Contracts\ActorResolver`. The default implementation reads the `actor_guard` guard. An admin panel integration binds its own:

```php
$this->app->bind(ActorResolver::class, MyPanelActorResolver::class);
```

## Security notes

- Running tasks and flushing failed jobs are powerful actions — protect the routes of your integration accordingly.
- Payloads are parsed without instantiating job classes (`unserialize` with an `allowed_classes` list), so listing failed jobs never runs foreign code or loads models.

## Testing

```bash
composer install
vendor/bin/pest
```

## License

MIT. See [LICENSE.md](LICENSE.md).
