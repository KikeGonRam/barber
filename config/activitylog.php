<?php

use App\Models\Activity;

return [
    'enabled' => env('ACTIVITY_LOGGER_ENABLED', true),

    'delete_records_older_than_days' => 365,

    'default_log_name' => env('ACTIVITY_LOGGER_DEFAULT_LOG_NAME', 'default'),

    'activity_model' => Activity::class,

    'table_name' => env('ACTIVITY_LOGGER_TABLE_NAME', 'activity_log'),

    'database_connection' => env('ACTIVITY_LOGGER_DB_CONNECTION', 'mongodb'),

    'subject_returns_soft_deleted_models' => false,

    'causer_returns_soft_deleted_models' => false,

    'default_auth_driver' => null,

    'activitylog_status' => env('ACTIVITY_LOGGER_ENABLED', true),
];
