# Laravel Jobs Monitor

Ядро, не зависящее от админки, для работы с **планировщиком** Laravel и **очередью на базе БД** без консоли:

- задачи планировщика с расписанием в понятном виде, временем следующего запуска и признаком «выполняется сейчас» — как `schedule:list`;
- ручной запуск задачи: отправить её job в очередь или выполнить прямо сейчас;
- обзор очереди (ожидающие / выполняющиеся / отложенные / упавшие, по каждой очереди) и список задач в очереди;
- упавшие задачи с ошибкой, стектрейсом, payload и **адресом получателя уведомления**;
- повтор, удаление, очистка — как `queue:retry`, `queue:forget`, `queue:flush`;
- журнал всех ручных действий с id пользователя, который их выполнил;
- переводы на английский и русский.

Интерфейса в пакете нет. Используйте интеграцию с админкой:

| Админка | Пакет |
|---|---|
| Backpack for Laravel | [`mikhailmelnikov/backpack-jobs-monitor`](https://github.com/MikhailMelnikov/backpack-jobs-monitor) |
| Filament | [`mikhailmelnikov/filament-jobs-monitor`](https://github.com/MikhailMelnikov/filament-jobs-monitor) |

## Требования

- PHP 8.3+
- Laravel 12 или 13
- для страниц очереди — драйвер очереди `database` (страницы планировщика работают с любым драйвером)

## Установка

```bash
composer require mikhailmelnikov/laravel-jobs-monitor
```

Сервис-провайдер подключается через package discovery. Если вы ставите интеграцию с админкой (например, `backpack-jobs-monitor`), ядро установится как её зависимость — отдельно его требовать не нужно.

При необходимости опубликуйте конфиг и переводы:

```bash
php artisan vendor:publish --tag=jobs-monitor-config
php artisan vendor:publish --tag=jobs-monitor-lang
```

<a id="configuration"></a>
## Настройка

`config/jobs-monitor.php`:

| Ключ | По умолчанию | Назначение |
|---|---|---|
| `queue_connection` | `null` | Подключение очереди, чьи таблицы показываются. `null` — `queue.default`, если оно использует драйвер database, иначе `database`. Имена таблиц и подключение к БД берутся из `config/queue.php`. |
| `per_page` | `25` | Размер страницы в списках задач в очереди и упавших задач. |
| `recipients.enabled` | `true` | Определять адрес получателя у упавших уведомлений. |
| `recipients.attribute` | `email` | Атрибут модели, в котором хранится адрес. |
| `sync_time_limit` | `0` | `set_time_limit()` для запуска «сейчас»; `null` — не менять. |
| `log_channel` | `null` | Канал журнала действий. |
| `actor_guard` | `null` | Guard для стандартного `ActorResolver`. |

## Использование (для интеграций)

Каждый сценарий — класс с аргументами конструктора и методом `handle()`. Зависимости необязательны и берутся из контейнера.

```php
use MikhailMelnikov\JobsMonitor\Enum\RunMode;
use MikhailMelnikov\JobsMonitor\Exceptions\JobsMonitorException;
use MikhailMelnikov\JobsMonitor\UseCase;

$tasks    = (new UseCase\GetScheduledTasksHandler())->handle();   // ScheduledTaskDto[]
$overview = (new UseCase\GetQueueOverviewHandler())->handle();    // счётчики
$queued   = (new UseCase\GetQueuedJobsHandler())->handle();       // пагинатор QueuedJobDto
$page2    = (new UseCase\GetQueuedJobsHandler(perPage: 50, page: 2))->handle(); // страница явно, без query string
$failed   = (new UseCase\GetFailedJobsHandler())->handle();       // пагинатор FailedJobDto

try {
    $message = (new UseCase\RunScheduledTaskHandler($taskId, RunMode::QUEUE))->handle();
    // также: DeleteQueuedJobHandler($id), RetryFailedJobsHandler([$uuid]), RetryFailedJobsHandler(),
    //        DeleteFailedJobHandler($uuid), FlushFailedJobsHandler()
} catch (JobsMonitorException $e) {
    // ожидаемый отказ (задача пропала, уже выполняется, …): сообщение переведено, его можно показать как предупреждение
}
```

Сценарии-действия возвращают переведённое сообщение об успехе.

Хендлеры не `final`: их можно унаследовать и подменить в контейнере (`$this->app->bind(RetryFailedJobsHandler::class, MyRetryFailedJobsHandler::class)`). Интеграция должна создавать хендлеры через контейнер (`app()->make(Handler::class, ['id' => $id])`), чтобы подмена работала, — так делает `backpack-jobs-monitor`.

### Кто выполнил действие

Ручные действия записываются в журнал с id текущего пользователя, который определяется через `MikhailMelnikov\JobsMonitor\Contracts\ActorResolver`. Стандартная реализация читает guard из `actor_guard`. Интеграция с админкой регистрирует свою:

```php
$this->app->bind(ActorResolver::class, MyPanelActorResolver::class);
```

## Безопасность

- Запуск задач и очистка упавших задач — мощные действия, защитите маршруты своей интеграции соответствующим образом.
- Payload разбирается без создания объектов job-классов (`unserialize` со списком `allowed_classes`), поэтому вывод упавших задач никогда не выполняет чужой код и не загружает модели.

## Тесты

```bash
composer install
vendor/bin/pest
```

## Лицензия

MIT. См. [LICENSE.md](LICENSE.md).
