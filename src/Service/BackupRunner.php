<?php

declare(strict_types=1);

namespace Lyrasoft\Backup\Service;

use FilesystemIterator;
use Firebase\JWT\JWT;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Windwalker\Utilities\Options\OptionAccessTrait;
use Windwalker\Utilities\StrNormalize;
use ZipStream\ZipStream;

class BackupRunner
{
    use OptionAccessTrait;

    public function __construct(array $options = [])
    {
        $this->options = $options;
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
            [$proc, $pipe] = $this->sqlDump();

            // $stream = new CachingStream(new Stream($pipe));

            $zip->addFileFromStream(
                $this->getOption('sql_file_name') ?? 'site-sql-backup.sql',
                $pipe
            );

            // $stream->close();

            if (proc_close($proc) !== 0) {
                throw new \RuntimeException('DB error');
            }
        }

        if ($this->getOption('dump_files')) {
            $this->zipFiles($zip);
        }

        return $zip->finish();
    }

    /**
     * @return  resource[]
     */
    protected function sqlDump(): array
    {
        $pass = '';

        if ($p = $this->options['database']['pass'] ?? '') {
            $pass = "-p\"$p\"";
        }

        $cmd = sprintf(
            '%s -h %s -u %s %s %s %s',
            $this->options['mysqldump'] ?? 'mysqldump',
            $this->options['database']['host'] ?? '',
            $this->options['database']['user'] ?? '',
            $pass,
            $this->options['database']['dbname'] ?? '',
            $this->options['mysqldump_extra'] ?? ''
        );

        $descriptorspec = [
            0 => ["pipe", "r"],   // stdin is a pipe that the child will read from
            1 => ["pipe", "w"],   // stdout is a pipe that the child will write to
            2 => ["pipe", "w"]    // stderr is a pipe that the child will write to
        ];

        $process = proc_open($cmd, $descriptorspec, $pipes, getcwd(), []);

        return [$process, $pipes[1]];
    }

    protected function zipFiles(ZipStream $zip): void
    {
        $root = realpath($this->getOption('root'));

        foreach (static::globAll($root, $this->options['pattern']) as $file) {
            if (is_dir($file)) {
                continue;
            }

            $dest = str_replace($root . DIRECTORY_SEPARATOR, '', $file);

            $zip->addFileFromPath(str_replace('\\', '/', $dest), $file);
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
}
