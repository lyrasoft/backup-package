<?php

/**
 * Part of backup-script project.
 *
 * @copyright  Copyright (C) 2020 ${ORGANIZATION}.
 * @license    __LICENSE__
 */

copy('https://raw.githubusercontent.com/lyrasoft/backup-script/master/backup.php', 'backup.php');

$file = __DIR__ . '/backup.php';
$content = file_get_contents($file);
$content = str_replace('{{ secret }}', bin2hex(random_bytes(16)), $content);

$config = [];

$y = ['y', '1', 'yes'];

if (in_array(strtolower(ask("Do you want to use DB? [Y/n]") ?: 'y'), $y, true)) {
    $host = ask("Host[localhost]: ") ?: 'localhost';
    $name = ask("DB Name: ");
    $user = ask("User[root]: ") ?: 'root';

    fwrite(STDOUT, "Password: ");
    system('stty -echo');

    $password = trim(fgets(STDIN));

    system('stty echo');

    $content = str_replace(
        [
            "'host' => 'localhost'",
            "'user' => ''",
            "'pass' => ''",
            "'name' => ''",
        ],
        [
            "'host' => '$host'",
            "'user' => '$user'",
            "'pass' => '$password'",
            "'name' => '$name'",
        ],
        $content
    );
}

$content = file_put_contents($file, $content);

[$self] = get_included_files();
unlink($self);

fwrite(STDOUT, "\nSuccess install backup.php file.");

function ask($question) {
    fwrite(STDOUT, $question);
    return trim(fgets(STDIN), "\n");
}
