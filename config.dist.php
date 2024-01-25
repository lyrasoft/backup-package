<?php

declare(strict_types=1);

return [
    /*
     * Basic Information
     */
    'secret' => '{{ secret }}',
    'name' => '',
    'root' => '.',

    'dump_database' => 0,

    'database' => [
        'host' => 'localhost',
        'user' => '',
        'pass' => '',
        'dbname' => '',
    ],

    'dump_files' => 0,

    'pattern' => [
        '/**/*',
        '!/node_modules/**',
        '!/vendor/**',
        '!/.git/**',
        '!/logs/*',
        '!/cache/*',
        '!/tmp/*',
    ],

    'mysqldump_binary' => null,
    'mysqldump_extra' => ''
];
