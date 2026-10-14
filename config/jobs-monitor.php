<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Monitored queue connection
    |--------------------------------------------------------------------------
    |
    | The queue connection (from config/queue.php) whose `database` driver
    | tables are shown. null means `queue.default` when it uses the database
    | driver, otherwise the `database` connection.
    |
    */

    'queue_connection' => null,

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    'per_page' => 25,

    /*
    |--------------------------------------------------------------------------
    | Failed notification recipients
    |--------------------------------------------------------------------------
    |
    | Failed notification jobs keep the notifiable as a model reference.
    | The package loads the recipient's current address from this attribute
    | (one query per model class per page) and shows it next to the failure.
    |
    */

    'recipients' => [
        'enabled' => true,
        'attribute' => 'email',
    ],

    /*
    |--------------------------------------------------------------------------
    | Synchronous run time limit
    |--------------------------------------------------------------------------
    |
    | set_time_limit() value applied when a scheduled task is executed right
    | inside the HTTP request. 0 means no limit, null leaves it untouched.
    |
    */

    'sync_time_limit' => 0,

    /*
    |--------------------------------------------------------------------------
    | Audit log
    |--------------------------------------------------------------------------
    |
    | Manual actions (run, retry, delete, flush) are logged together with the
    | id of the user who performed them. null uses the default log channel.
    | The user is resolved through the ActorResolver contract; admin panel
    | integrations bind their own implementation, otherwise `actor_guard`
    | (null — the default guard) is used.
    |
    */

    'log_channel' => null,

    'actor_guard' => null,

];
