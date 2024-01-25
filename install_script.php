<?php

error_reporting(E_ALL);

$content = file_get_contents(__DIR__ . '/config.dist.php');
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
    $dbname = ask("DB Name: ");
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
            "'dbname' => ''",
        ],
        [
            "'dump_database' => 1",
            "'host' => '$host'",
            "'user' => '$user'",
            "'pass' => '$password'",
            "'dbname' => '$dbname'",
        ],
        $content
    );
}

$content = file_put_contents(__DIR__ . '/config.php', $content);

fwrite(STDOUT, "\nSuccess install backup.php file.\n\n");

if (in_array(strtolower(ask("Register backup to portal? [Y/n]") ?: 'y'), $y, true)) {
    exec('php ./backup.php register');
}

fwrite(STDOUT, "\n");

function ask($question)
{
    fwrite(STDOUT, $question);
    return trim(fgets(STDIN), "\r\n");
}
