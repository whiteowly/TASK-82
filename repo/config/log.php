<?php

return [
    'default'  => 'file',
    'channels' => [
        'file' => [
            'type'        => 'file',
            'path'        => runtime_path() . 'log' . DIRECTORY_SEPARATOR,
            'single'      => false,
            'file_size'   => 2097152,
            'time_format' => 'c',
            'format'      => '[%s][%s] %s',
            'realtime_write' => false,
        ],
    ],
];
