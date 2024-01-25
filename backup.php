<?php

use Lyrasoft\Backup\BackupApp;

$autoload = __DIR__ . '/vendor/autoload.php';

if (!is_file($autoload)) {
    $autoload = __DIR__ . '/../../autoload.php';
}

include $autoload;

// Uncomment if debugging
// error_reporting(-1);

$configFile = __DIR__ . '/config.php';
$options = [];

if (is_file($configFile)) {
    $options = include $configFile;
}

// Set error handler
BackupApp::registerErrorHandler();

$app = new BackupApp($options);

$app->execute(PHP_SAPI);
