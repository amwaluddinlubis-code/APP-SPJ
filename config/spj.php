<?php

return [
    'arkas_bridge_command' => env('SPJ_ARKAS_BRIDGE_COMMAND'),
    'school_mode' => env('SPJ_SCHOOL_MODE', 'single-school'),
    'fiscal_year_mode' => env('SPJ_FISCAL_YEAR_MODE', 'single-year'),
    'backup_retention' => (int) env('SPJ_BACKUP_RETENTION', 30),
    'database_manager_sensitive_columns' => [
        'password',
        'token',
        'secret',
        'key',
        'credential',
    ],
];
