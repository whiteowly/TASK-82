<?php

$bootstrap = bootstrap_config();

return [
    'default'     => 'mysql',
    'connections'  => [
        'mysql' => [
            'type'     => 'mysql',
            'hostname' => $bootstrap['db_host'] ?? '',
            'database' => $bootstrap['db_database'] ?? '',
            'username' => $bootstrap['db_username'] ?? '',
            'password' => $bootstrap['db_password'] ?? '',
            'hostport' => (string)($bootstrap['db_port'] ?? ''),
            'charset'  => 'utf8mb4',
            'prefix'   => '',
            'debug'    => true,
            'deploy'   => 0,
            'rw_separate' => false,
            'fields_strict' => true,
            'fields_cache'  => false,
            'trigger_sql'   => true,
            'auto_timestamp' => true,
            'datetime_format' => 'Y-m-d H:i:s',
        ],
    ],
];
