<?php

declare(strict_types=1);

namespace Lyrasoft\Backup\Module\Backup;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Lyrasoft\Backup\BackupPackage;
use Lyrasoft\Backup\Service\BackupRunner;
use Windwalker\Core\Application\AppContext;
use Windwalker\Core\Attributes\Controller;
use Windwalker\Http\Helper\HeaderHelper;
use Windwalker\Http\Output\Output;
use Windwalker\Http\Response\Response;
use Windwalker\Utilities\StrNormalize;

use function Windwalker\now;

#[Controller]
class BackupController
{
    public function backup(AppContext $app, BackupPackage $backupPackage)
    {
        $auth = $app->getAppRequest()->getHeader('authorization');

        sscanf($auth, 'Bearer %s', $token);

        if (!$token) {
            $token = $app->input('token');
        }

        if (!$token) {
            return 'Invalid Token';
        }

        $profile = $app->input('profile') ?: 'default';

        $options = $app->config('backup.profiles.' . $profile);

        if ($options === null) {
            throw new \RuntimeException("Backup profile: $profile not found.");
        }

        $options['sql_file_name'] ??= $this->getSqlFileName($app);
        $options['secret'] = $backupPackage->getSecret();
        $options['root'] = WINDWALKER_ROOT;

        $runner = new BackupRunner($options);

        $payload = (array) JWT::decode($token, new Key($backupPackage->getSecret(), 'HS512'));

        if ($payload['iss'] !== 'lyra-backup' || $payload['sub'] !== 'backup-token') {
            return 'Invalid Token';
        }

        $res = HeaderHelper::prepareAttachmentHeaders(
            new Response(),
            $runner->getBackupFilename($app->getAppName())
        );
        $res = $res->withHeader('Content-Type', 'application/zip');
        $output = new Output();
        $output->sendHeaders($res);

        $runner->backup('php://output');
        die;
    }

    protected function getSqlFileName(AppContext $app): string
    {
        $appName = $app->getAppName();
        $time = now('Y-m-d-H-i-s');

        if ($appName) {
            return StrNormalize::toKebabCase($appName) . "-sql-backup-$time.sql";
        }

        return "site-sql-backup-$time.sql";
    }
}
