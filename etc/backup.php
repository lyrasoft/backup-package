<?php

declare(strict_types=1);

return [
    'backup' => [
        'profiles' => [
            'default' => [
                /*
                 * Basic Information
                 */
                'secret' => '{{ secret }}',
                'name' => '',
                'root' => '.',

                'dump_database' => 0,

                'database' => [
                    'host' => env('DATABASE_HOST'),
                    'user' => env('DATABASE_USER'),
                    'pass' => env('DATABASE_PASSWORD'),
                    'dbname' => env('DATABASE_NAME'),
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

                'config' => 'backup_config.php',
                'mysqldump' => 'mysqldump',
                'mysqldump_extra' => ''
            ]
        ]
    ]
];
