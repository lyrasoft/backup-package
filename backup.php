<?php
/**
 * LYRASOFT backup script.
 *
 * @copyright  Copyright (C) 2015 LYRASOFT. All rights reserved.
 */

use GuzzleHttp\Psr7\CachingStream;
use GuzzleHttp\Psr7\Stream;
use ZipStream\ZipStream;

include __DIR__ . '/vendor/autoload.php';

// Uncomment if debugging
// error_reporting(-1);

$options = [
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

    'config' => 'backup_config.php',
    'mysqldump' => 'mysqldump',
    'mysqldump_extra' => ''
];

// Set error handler
BackupApplication::registerErrorHandler();

$app = new BackupApplication($options);

$app->execute(PHP_SAPI);
