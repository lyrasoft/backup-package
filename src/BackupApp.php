<?php

declare(strict_types=1);

namespace Lyrasoft\Backup;

use ErrorException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Lyrasoft\Backup\Script\CliBackupCommand;
use Lyrasoft\Backup\Script\CliRegisterCommand;
use Lyrasoft\Backup\Script\CliTokenCommand;
use Lyrasoft\Backup\Service\BackupRunner;

class BackupApp
{
    protected string $sapi = '';

    /**
     * @var  array
     */
    protected array $options = [];

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

            $this->downloadBackup();
        } catch (\Throwable $e) {
            $msg = isset($this->cli['options']['v']) ? (string) $e : $e->getMessage();

            $this->close($msg, $e->getCode());
        }
    }

    public function executeCli(): void
    {
        $console = new BackupCli('LYRASOFT Backup', options: $this->options);
        $console->setDefaultCommand('backup');
        $console->add(new CliBackupCommand());
        $console->add(new CliTokenCommand());
        $console->add(new CliRegisterCommand());

        exit($console->run());
    }

    /**
     * @return  void
     */
    protected function downloadBackup(): void
    {
        $runner = new BackupRunner($this->options);

        $this->authenticate();

        $name = rawurldecode($this->getBackupFilename());
        header('Content-Type: application/x-zip');
        header("Content-Disposition: attachment; filename*=UTF-8''{$name}");
        header('Pragma: public');
        header('Cache-Control: public, must-revalidate');
        header('Content-Transfer-Encoding: binary');

        $runner->backup('php://output');
    }

    public function authenticate(): bool
    {
        $token = '';

        if ($auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '') {
            sscanf($auth, 'Bearer %s', $token);
        }

        $token = $token ?: $_REQUEST['token'] ?? $this->close('Invalid Token');

        $payload = (array) JWT::decode(
            $token,
            new Key($this->getOption('secret'), 'HS512')
        );

        if ($payload['iss'] !== 'lyra-backup' || $payload['sub'] !== 'backup-token') {
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
}
