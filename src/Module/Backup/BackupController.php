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

        $runner = new BackupRunner($options);

        $payload = (array) JWT::decode($token, new Key($backupPackage->getSecret(), 'HS512'));

        if ($payload['iss'] !== 'lyra-backup' || $payload['sub'] !== 'backup-token') {
            return 'Invalid Token';
        }

        $res = HeaderHelper::prepareAttachmentHeaders(
            new Response(),
            $runner->getBackupFilename($app->getAppName())
        );
        $output = new Output();
        $output->sendHeaders($res);

        $runner->backup('php://output');
        die;
    }
}
