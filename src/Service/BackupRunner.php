<?php

declare(strict_types=1);

namespace Lyrasoft\Backup\Service;

use FilesystemIterator;
use Firebase\JWT\JWT;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Pipes\UnixPipes;
use Symfony\Component\Process\Pipes\WindowsPipes;
use Symfony\Component\Process\Process;
use Windwalker\Utilities\StrNormalize;
use ZipStream\ZipStream;

class BackupRunner
{
    public function __construct(protected array $options = [])
    {
        //
    }

    public function token(): string
    {
        $secret = (string) $this->getOption('secret');

        if (!$secret) {
            throw new \RuntimeException('No secret');
        }

        return JWT::encode(
            [
                'iss' => 'lyra-backup',
                // 'exp' => time() + (60 * 15),
                'sub' => 'backup-token',
            ],
            $secret,
            'HS512'
        );
    }

    public function registerData(string $title, string $name, string $url)
    {
        return [
            'title' => $title,
            'name' => $name,
            'url' => $url . '/backup'
        ];
    }

    public function nas(string $name, string $url): string
    {
        $name = StrNormalize::toKebabCase($name);
        $token = $this->token();

        return "\nNAS script:\n  curl -sSf --create-dirs -X POST $url --data \"token=$token\" " .
            "-o /volume1/backup/$(date +%Y/%m/%d)/$name-backup-$(date +%Y-%m-%d).zip -k\n\n";
    }

    public function backup(mixed $outputStream): int
    {
        set_time_limit(0);
        ini_set('memory_limit', '1G');

        $zip = new ZipStream(
            outputStream: $outputStream,
            sendHttpHeaders: false
        );

        if ($this->getOption('dump_database') ?? true) {
            $this->zipSql($zip);
        }

        if ($this->getOption('dump_files')) {
            $this->zipFiles($zip);
        }

        return $zip->finish();
    }

    protected function zipSql(ZipStream $zip): void
    {
        $process = $this->sqlDump();

        $process->start();
        $ref = new \ReflectionObject($process);
        $prop = $ref->getProperty('processPipes');

        /**
         * @var $pipes WindowsPipes|UnixPipes
         */
        $pipes = $prop->getValue($process);

        if ($pipes instanceof WindowsPipes) {
            $fp = fopen($pipes->getFiles()[1], 'rb');
            sleep(1);
        } else {
            $fp = $pipes->pipes[1];
        }

        $zip->addFileFromStream(
            $this->getOption('sql_file_name') ?? 'site-sql-backup.sql',
            $fp
        );

        $process->stop();

        // if (proc_close($proc) !== 0) {
        //     throw new \RuntimeException('DB error');
        // }
    }

    /**
     * @return  resource[]
     */
    protected function sqlDump(): Process
    {
        $pass = '';

        if ($p = $this->options['database']['pass'] ?? '') {
            $pass = "-p\"$p\"";
        }

        $cmd = sprintf(
            '%s -h %s -u %s %s %s %s --no-tablespaces ',
            $this->findMysqldump(),
            $this->options['database']['host'] ?? '',
            $this->options['database']['user'] ?? '',
            $pass,
            $this->options['database']['dbname'] ?? '',
            $this->options['mysqldump_extra'] ?? ''
        );

        return Process::fromShellCommandline($cmd);
    }

    protected function zipFiles(ZipStream $zip): void
    {
        $root = $this->getOption('root');

        foreach (static::globAll($root, $this->options['pattern']) as $file) {
            if (
                $file->isDir()
                || $file->isLink()
                || $this->isJunction($file->getPathname())
            ) {
                continue;
            }

            $dest = str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname());

            $zip->addFileFromPath(
                str_replace('\\', '/', $dest),
                $file->getPathname()
            );
        }
    }

    public static function globAll(string $baseDir, array $patterns): \Generator
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS)
        );

        $exists        = [];
        $allowPatterns = [];
        $denyPatterns  = [];

        foreach ($patterns as $pattern) {
            if (strpos($pattern, '!') === 0) {
                $pattern = substr($pattern, 1);

                $denyPatterns[] = $pattern;
            } else {
                $allowPatterns[] = $pattern;
            }
        }

        /** @var \SplFileInfo $item */
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                continue;
            }

            if (in_array($item->getPathname(), $exists, true)) {
                continue;
            }

            $file = substr($item->getPathname(), strlen(rtrim($baseDir, '/')));
            // fnmatch() only work for UNIX file path
            $file = str_replace(['/', '\\'], '/', $file);

            $match = false;

            foreach ($allowPatterns as $allowPattern) {
                if (fnmatch($allowPattern, $file)) {
                    $exists[] = $item->getPathname();
                    $match = true;
                    break;
                }
            }

            if ($match) {
                $deny = false;

                foreach ($denyPatterns as $denyPattern) {
                    // print_r([$denyPattern, $file, fnmatch($denyPattern, $file)]);
                    $deny = fnmatch($denyPattern, $file) || $deny;
                }

                if (!$deny) {
                    yield $item;
                }
            }
        }
    }

    public function getBackupFilename(?string $appName): string
    {
        if (!isset($_SERVER['HTTP_HOST']) && !$appName) {
            return 'backup';
        }

        $base = $appName ?: $_SERVER['HTTP_HOST'] . '/' . $_SERVER['SCRIPT_NAME'];

        $str = str_replace('-', ' ', $base);

        if (function_exists('mb_strtolower')) {
            $str = mb_strtolower(trim($str));
        } else {
            $str = strtolower(trim($str));
        }

        $str = preg_replace('/(\s|[^A-Za-z0-9\-])+/', '-', $str);

        return trim($str, '-') . '.zip';
    }

    public function isJunction(string $junction): bool
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            return false;
        }

        // Important to clear all caches first
        clearstatcache(true, $junction);

        if (!is_dir($junction) || is_link($junction)) {
            return false;
        }

        $stat = lstat($junction);

        // S_ISDIR test (S_IFDIR is 0x4000, S_IFMT is 0xF000 bitmask)
        return is_array($stat) ? 0x4000 !== ($stat['mode'] & 0xF000) : false;
    }

    protected function findMysqldump(): string
    {
        if ($md = (string) env('MYSQLDUMP_BINARY')) {
            return $md;
        }

        if ($md = (string) $this->getOption('mysqldump_binary')) {
            return $md;
        }

        $process = Process::fromShellCommandline('which mysqldump');
        $process->run();

        if ($process->isSuccessful()) {
            return $process->getOutput();
        }

        $pos = [];

        if (DIRECTORY_SEPARATOR === '\\') {
            $pos = [
                'C:\xampp\mysql\bin\mysqldump.exe',
            ];
        } elseif (static::isUnix()) {
            $pos = [
                '/Applications/XAMPP/xamppfiles/bin/mysqldump',
                '/Applications/AMPPS/bin/mysqldump',
            ];
        }

        foreach ($pos as $md) {
            if (is_file($md) && is_executable($md)) {
                return $md;
            }
        }

        return 'mysqldump';
    }

    public static function isUnix(): bool
    {
        $unames = [
            'CYG',
            'DAR',
            'FRE',
            'HP-',
            'IRI',
            'LIN',
            'NET',
            'OPE',
            'SUN',
            'UNI',
        ];

        return in_array(strtoupper(substr(PHP_OS, 0, 3)), $unames);
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function getOption(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }

    public function setOption(string $name, mixed $value): static
    {
        $this->options[$name] = $value;

        return $this;
    }
}
