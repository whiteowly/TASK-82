<?php

return [
    'default' => 'file',
    'stores'  => [
        'file' => [
            'type'       => 'File',
            'path'       => runtime_path() . 'cache' . DIRECTORY_SEPARATOR,
            'prefix'     => '',
            'expire'     => 0,
            'serialize'  => [],
            'tag_prefix' => 'tag:',
        ],
    ],
];
