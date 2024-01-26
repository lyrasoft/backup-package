<?php

declare(strict_types=1);

namespace Lyrasoft\Backup;

use Lyrasoft\Backup\Command\BackupRegisterCommand;
use Lyrasoft\Backup\Command\BackupRunCommand;
use Lyrasoft\Backup\Command\BackupTokenCommand;
use Windwalker\Core\Application\ApplicationInterface;
use Windwalker\Core\Package\AbstractPackage;
use Windwalker\Core\Package\PackageInstaller;
use Windwalker\DI\Container;
use Windwalker\DI\ServiceProviderInterface;

class BackupPackage extends AbstractPackage implements ServiceProviderInterface
{
    public function __construct(protected ApplicationInterface $app)
    {
    }

    public function install(PackageInstaller $installer): void
    {
        $installer->installConfig(static::path('etc/*.php'), 'config');
        $installer->installConfig(static::path('routes/**/*.php'), 'routes');
    }

    public function getSecret(): string
    {
        if (!method_exists($this->app, 'getSecret')) {
            return (string) $this->app->config('app.secret')
                ?: throw new \RuntimeException('This site has no secret');
        }

        return $this->app->getSecret();
    }

    /**
     * @inheritDoc
     */
    public function register(Container $container): void
    {
        $container->mergeParameters(
            'commands',
            [
                'backup:run' => BackupRunCommand::class,
                'backup:token' => BackupTokenCommand::class,
                'backup:register' => BackupRegisterCommand::class,
            ]
        );
    }
}
