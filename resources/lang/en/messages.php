<?php

return [
    'unknown_job' => 'Unknown job',

    'queued_job_not_found' => 'The job was not found — a worker may have already processed it.',
    'queued_job_reserved' => 'The job is already being processed by a worker and cannot be deleted.',
    'queued_job_deleted' => 'The job was removed from the queue.',

    'failed_job_not_found' => 'The failed job was not found — it may have already been deleted.',
    'failed_job_deleted' => 'The failed job record was deleted.',
    'failed_jobs_flushed' => 'All failed jobs were deleted.',
    'failed_job_retried' => 'The job was pushed back to the queue.',
    'failed_jobs_retried' => 'All failed jobs were pushed back to the queue.',

    'task_not_found' => 'The task was not found in the scheduler.',
    'task_already_running' => 'Task “:name” is already running, the run was cancelled.',
    'task_executed' => 'Task “:name” has been executed.',
    'task_queued' => 'Task “:name” was pushed to the “:queue” queue.',
];
