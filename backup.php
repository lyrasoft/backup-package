<?php

use Lyrasoft\Backup\BackupApp;

$autoload = __DIR__ . '/vendor/autoload.php';

if (!is_file($autoload)) {
    $autoload = __DIR__ . '/../../autoload.php';
}

include $autoload;

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
BackupApp::registerErrorHandler();

$app = new BackupApp($options);

$app->execute(PHP_SAPI);
