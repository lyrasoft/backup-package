<?php

/**
 * Part of backup-script project.
 *
 * @copyright  Copyright (C) 2020 ${ORGANIZATION}.
 * @license    __LICENSE__
 */

$file = __DIR__ . '/backup.php';
$content = file_get_contents($file);
$content = str_replace('{{ secret }}', $secret = bin2hex(random_bytes(16)), $content);

$config = [];

$y = ['y', '1', 'yes'];

$pname = ask('Project Name: ');

$content = str_replace(
    "'name' => ''",
    "'name' => '$pname'",
    $content
);

if (in_array(strtolower(ask("Do you want to dump Files? [Y/n]") ?: 'y'), $y, true)) {
    $root = ask('Backup Root[.]: ') ?: '.';

    $content = str_replace(
        [
            "'dump_files' => 0",
            "'root' => '.'"
        ],
        [
            "'dump_files' => 1",
            "'root' => '$root'"
        ],
        $content
    );
}

if (in_array(strtolower(ask("Do you want to dump DB? [Y/n]") ?: 'y'), $y, true)) {
    $host = ask("Host[localhost]: ") ?: 'localhost';
    $name = ask("DB Name: ");
    $user = ask("User[root]: ") ?: 'root';

    fwrite(STDOUT, "Password: ");
    system('stty -echo');

    $password = trim(fgets(STDIN));

    system('stty echo');

    $content = str_replace(
        [
            "'dump_database' => 0",
            "'host' => 'localhost'",
            "'user' => ''",
            "'pass' => ''",
            "'name' => ''",
        ],
        [
            "'dump_database' => 1",
            "'host' => '$host'",
            "'user' => '$user'",
            "'pass' => '$password'",
            "'name' => '$name'",
        ],
        $content
    );
}

$content = file_put_contents($file, $content);
$token = sha1(md5('LYRASOFT:' . $secret));

[$self] = get_included_files();
unlink($self);

fwrite(STDOUT, "\nSuccess install backup.php file.");
fwrite(STDOUT, "\nToken: $token\n");

if (in_array(strtolower(ask("Show NAS download script? [Y/n]") ?: 'y'), $y, true)) {
    $url = ask('Site URL: ') ?: '{https://site.com}';
    $url = rtrim($url, '/') . '/backup.php';

    fwrite(
        STDOUT,
        "\nNAS script:\n  curl -sSf --create-dirs -X POST $url --data \"token=$token\" -o /volume1/backup/$(date +%Y/%m/%d)/$pname-backup-$(date +%Y-%m-%d).zip\n\n"
    );
}

fwrite(STDOUT, "\n");

function ask($question) {
    fwrite(STDOUT, $question);
    return trim(fgets(STDIN), "\n");
}
