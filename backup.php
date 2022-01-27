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
        'name' => '',
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
];

class BackupApplication
{
    protected $sapi = '';

    protected $cli = [
        'file' => [],
        'args' => [],
        'options' => [],
    ];

    /**
     * @var  array
     */
    protected $options = [];

    /**
     * Class init
     *
     * @param  array  $options
     */
    public function __construct(array $options)
    {
        $this->options = $options;

        // Override
        if (is_file(__DIR__ . '/' . $this->options['config'])) {
            $override = require __DIR__ . '/' . $this->options['config'];

            $this->options = array_merge($this->options, $override);
        }

        $this->options['root'] = realpath($path = __DIR__ . '/' . trim($this->getOption('root'), '/'));

        if (!is_dir($this->options['root'])) {
            $this->close('Path: ' . $path . ' not exists');
        }
    }

    /**
     * execute
     *
     * @param  string  $sapi
     *
     * @return  void
     */
    public function execute(string $sapi): void
    {
        $this->sapi = $sapi;

        try {
            if ($sapi === 'cli') {
                $this->executeCli();

                return;
            }

            $this->authenticate();

            $this->doBackup();
        } catch (\Throwable $e) {
            $msg = isset($this->cli['options']['v']) ? (string) $e : $e->getMessage();

            $this->close($msg, $e->getCode());
        }
    }

    public function executeCli(): void
    {
        [$this->cli['file'], $this->cli['args'], $this->cli['options']] = $this->parseArgv($_SERVER['argv']);

        if (!empty($this->cli['options']['h'])) {
            $this->help();
        }

        if (($this->cli['args'][0] ?? null) === 'token') {
            echo $this->getToken($this->options['secret'] ?? $this->close('No secret', 400));
            $this->close('', 200);
        }

        if (($this->cli['args'][0] ?? null) === 'nas') {
            $token = $this->getToken($this->options['secret'] ?? $this->close('No secret', 400));
            $pname = $this->options['name'] ?: 'backup';

            $url = $this->ask('Site URL: ') ?: '{https://site.com}';
            $url = rtrim($url, '/') . '/backup.php';

            echo "\nNAS script:\n  curl -sSf --create-dirs -X POST $url --data \"token=$token\" -o /volume1/backup/$(date +%Y/%m/%d)/$pname-backup-$(date +%Y-%m-%d).zip -k\n\n";
            $this->close('', 200);
        }

        $this->doBackup('php://stdout');
        $this->close('', 200);
    }

    protected function help(): void
    {
        $file = $this->cli['file'];
        echo <<<HELP
LYRASOFT Backup script

Options:
    -h  Show help.
    -v  Show more error details.

Commands:
    >|to    Backup to this position.
    token   Show token for URL backup.
    nas     Show NAS download script.
    
Usages:
    php {$file} > /tmp/backup.zip   Backup to this file.
    php {$file} > /tmp/             Backup to this dir with default file name.
HELP;
        $this->close('', 200);
    }

    public function doBackup($output = 'php://output', bool $headers = true)
    {
        set_time_limit(0);
        ini_set('memory_limit', '1G');

        if ($headers) {
            $name = rawurldecode($this->getBackupFilename());
            header('Content-Type: application/x-zip');
            header("Content-Disposition: attachment; filename*=UTF-8''{$name}");
            header('Pragma: public');
            header('Cache-Control: public, must-revalidate');
            header('Content-Transfer-Encoding: binary');
        }

        $zip = new ZipStream($output);

        if ($this->getOption('dump_database', true)) {
            [$proc, $pipe] = $this->sqlDump();

            $stream = new CachingStream(new Stream($pipe));

            $zip->addFileFromPsr7Stream('site-sql-backup.sql', $stream);

            $stream->close();

            if (proc_close($proc) !== 0) {
                throw new \RuntimeException('DB error');
            }
        }

        if ($this->getOption('dump_files')) {
            $this->zipFiles($zip);
        }

        $zip->finish();
    }

    /**
     * @return  resource[]
     */
    protected function sqlDump(): array
    {
        $cmd = sprintf(
            '%s -h %s -u %s -p%s %s',
            $this->options['mysqldump'] ?? 'mysqldump',
            $this->cli['options']['host'] ?? $this->options['database']['host'] ?? '',
            $this->cli['options']['u'] ?? $this->options['database']['user'] ?? '',
            $this->cli['options']['p'] ?? $this->options['database']['pass'] ?? '',
            $this->cli['options']['db'] ?? $this->options['database']['name'] ?? ''
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

        foreach (FileFilter::globAll($root, $this->options['pattern']) as $file) {
            if (is_dir($file)) {
                continue;
            }

            $dest = str_replace($root . DIRECTORY_SEPARATOR, '', $file);

            $zip->addFileFromPath(str_replace('\\', '/', $dest), $file);
        }
    }

    public function authenticate(): bool
    {
        $token = $_REQUEST['token'] ?? $this->close('Invalid Token');

        $key = $this->getOption('secret') ?? $this->close('No secret');

        if ($this->getToken($key) !== $token) {
            $this->close('Invalid Token');
        }

        return true;
    }

    protected function getToken(string $secret): string
    {
        return sha1(md5('LYRASOFT:' . $secret));
    }

    public function getOption(string $key, $default = null)
    {
        return $this->options[$key] ?? $default;
    }

    public function close(string $msg, int $code = 401): void
    {
        if ($this->sapi === 'cli') {
            fwrite(STDERR, $msg);

            exit($code === 200 ? 0 : 255);
        }

        http_response_code($code);

        exit($msg);
    }

    public function getBackupFilename(): string
    {
        if (!isset($_SERVER['HTTP_HOST'])) {
            return 'backup';
        }

        $base = $_SERVER['HTTP_HOST'] . '/' . $_SERVER['SCRIPT_NAME'];

        $str = str_replace('-', ' ', $base);

        if (function_exists('mb_strtolower')) {
            $str = mb_strtolower(trim($str));
        } else {
            $str = strtolower(trim($str));
        }

        $str = preg_replace('/(\s|[^A-Za-z0-9\-])+/', '-', $str);

        return trim($str, '-') . '.zip';
    }

    public static function registerErrorHandler(): void
    {
        set_error_handler(
            function ($errno, $errstr, $errfile, $errline) {
                throw new ErrorException($errstr, $errno, 1, $errfile, $errline);
            }
        );
    }

    protected function parseArgv($argv)
    {
        $script = array_shift($argv);
        $key    = null;
        $args   = [];

        $options = [];

        for ($i = 0, $j = count($argv); $i < $j; $i++) {
            $arg = $argv[$i];

            // --foo --bar=baz
            if (0 === strpos($arg, '--')) {
                $eqPos = strpos($arg, '=');

                // --foo
                if ($eqPos === false) {
                    $key = substr($arg, 2);

                    // --foo value
                    if ($i + 1 < $j && $argv[$i + 1][0] !== '-') {
                        $value = $argv[$i + 1];
                        $i++;
                    } else {
                        $value = $options[$key] ?? true;
                    }

                    $options[$key] = $value;
                } else {
                    // --bar=baz
                    $key           = substr($arg, 2, $eqPos - 2);
                    $value         = substr($arg, $eqPos + 1);
                    $options[$key] = $value;
                }
            } elseif (0 === strpos($arg, '-')) {
                // -k=value -abc

                // -k=value
                if (isset($arg[2]) && $arg[2] === '=') {
                    $key           = $arg[1];
                    $value         = substr($arg, 3);
                    $options[$key] = $value;
                } else {
                    // -abc
                    $chars = str_split(substr($arg, 1));

                    foreach ($chars as $char) {
                        $key           = $char;
                        $options[$key] = isset($options[$key]) ? $options[$key] + 1 : 1;
                    }

                    // -a a-value
                    if (($i + 1 < $j) && ($argv[$i + 1][0] !== '-') && (count($chars) === 1)) {
                        $options[$key] = $argv[$i + 1];
                        $i++;
                    }
                }
            } else {
                // Plain-arg
                $args[] = $arg;
            }
        }

        return [$script, $args, $options];
    }

    public function ask(string $question)
    {
        fwrite(STDOUT, $question);
        return trim(fgets(STDIN), "\n");
    }
}

class FileFilter
{
    public static function globAll(string $baseDir, array $patterns): \Generator
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS)
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
}

// Set error handler
BackupApplication::registerErrorHandler();

$app = new BackupApplication($options);

$app->execute(PHP_SAPI);
