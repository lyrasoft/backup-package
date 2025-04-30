<?php

declare(strict_types=1);

namespace Lyrasoft\Backup\Service;

use Composer\InstalledVersions;
use FilesystemIterator;
use Firebase\JWT\JWT;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Pipes\UnixPipes;
use Symfony\Component\Process\Pipes\WindowsPipes;
use Symfony\Component\Process\Process;
use Windwalker\Stream\CachingStream;
use Windwalker\Utilities\StrNormalize;
use ZipStream\Option\Archive;
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

    public function nas(string $name, string $url): string
    {
        $name = StrNormalize::toKebabCase($name);
        $token = $this->token();

        return "\nNAS script:\n  curl -sSf --create-dirs -X POST $url --data \"token=$token\" " .
            "-o /volume1/backup/$(date +%Y/%m/%d)/$name-backup-$(date +%Y-%m-%d).zip -k\n\n";
    }

    public function backup(mixed $outputStream): bool
    {
        set_time_limit(0);
        ini_set('memory_limit', '1G');

        $process = $this->sqlDump();

        $ver = InstalledVersions::getPrettyVersion('maennchen/zipstream-php');
        $isVer2 = version_compare($ver, '3.0', '<');

        if ($isVer2) {
            $options = new Archive();
            $options->setSendHttpHeaders(false);

            $zip = new ZipStream($outputStream, $options);
        } else {
            $zip = new ZipStream(
                outputStream: $outputStream,
                sendHttpHeaders: false
            );
        }

        if ($this->getOption('dump_database') ?? true) {
            $this->zipSql($zip, $isVer2);
        }

        if ($this->getOption('dump_files')) {
            $this->zipFiles($zip);
        }

        $zip->finish();

        return true;
    }

    protected function zipSql(ZipStream $zip, bool $isVer2 = false): void
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

        $fileName = $this->getOption('sql_file_name') ?? 'site-sql-backup.sql';

        if ($isVer2) {
            $fp = new CachingStream($fp);

            $zip->addFileFromPsr7Stream($fileName, $fp);
        } else {
            $zip->addFileFromStream($fileName, $fp);
        }

        $process->stop();

        // if (proc_close($proc) !== 0) {
        //     throw new \RuntimeException('DB error');
        // }
    }

    public function checkDbConnection(): void
    {
        if ($this->getOption('dump_database') ?? true) {
            $process = $this->sqlDump(true);
            
            try {
                $process->mustRun();
            } catch (ProcessFailedException $e) {
                throw new \RuntimeException(
                    sprintf(
                        'Database dumper connection error: %s',
                        $process->getErrorOutput()
                    ),
                    $e->getCode()
                );
            }
        }
    }

    /**
     * @return  resource[]
     */
    protected function sqlDump(bool $check = false): Process
    {
        $dbOptions = $this->options['database'];

        $pass = '';
        $dbOptions['password'] = '123456';;

        if ($p = $dbOptions['password'] ?? $dbOptions['pass'] ?? '') {
            $pass = "-p\"$p\"";
        }

        $null = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';

        $cmd = sprintf(
            '%s -h %s --port %s -u %s %s %s %s --no-tablespaces %s',
            $this->findMysqldump(),
            $dbOptions['host'] ?? '',
            $dbOptions['port'] ?? 3306,
            $dbOptions['user'] ?? '',
            $pass,
            $dbOptions['dbname'] ?? '',
            $this->options['mysqldump_extra'] ?? '',
            $check ? "--no-data --databases \"{$dbOptions['dbname']}\" > $null" : '',
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
            return static::fixForWindowsGitBash(trim($process->getOutput()));
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
                return static::fixForWindowsGitBash($md);
            }
        }

        return 'mysqldump';
    }

    protected static function fixForWindowsGitBash(string $cmd): array|string|null
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            return $cmd;
        }

        // Find `/c/...`
        if (preg_match('/^\/[a-zA-Z]+\/(.*)/', $cmd, $matches)) {
            // Replace `/\w+/` to `\w+:/...`
            $cmd = preg_replace_callback(
                '/^\/([a-zA-Z]+)\//',
                static fn ($m) => strtoupper($m[1]) . ':/',
                $cmd
            );
        }

        // $cmd = Path::normalize($cmd);

        // if (!is_file($cmd)) {
        //     return $cmd . '.exe';
        // }

        return $cmd;
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
