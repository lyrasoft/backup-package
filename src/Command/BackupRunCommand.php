<?php

declare(strict_types=1);

namespace Lyrasoft\Backup\Command;

use Lyrasoft\Backup\BackupPackage;
use Lyrasoft\Backup\Service\BackupRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Windwalker\Core\Application\ApplicationInterface;
use Windwalker\Core\Manager\DatabaseManager;
use Windwalker\DI\Attributes\Inject;
use Windwalker\Utilities\StrNormalize;

use function Windwalker\now;

#[AsCommand(
    'backup:run',
    'Run backup.'
)]
class BackupRunCommand extends Command
{
    #[Inject]
    protected ApplicationInterface $app;

    #[Inject]
    protected BackupPackage $backupPackage;

    #[Inject]
    protected DatabaseManager $databaseManager;

    #[\ReturnTypeWillChange]
    protected function configure()
    {
        $this->addArgument(
            'profile',
            InputArgument::OPTIONAL,
            'The backup setting profile name.',
            'default'
        );

        $this->addOption(
            'output',
            'o',
            InputOption::VALUE_REQUIRED,
            'Save to path.',
            'php://stdout'
        );
    }

    #[\ReturnTypeWillChange]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $profile = $input->getArgument('profile');
        $backupOutput = $input->getOption('output');

        $options = $this->app->config('backup.profiles.' . $profile);

        if ($options === null) {
            throw new \RuntimeException("Backup profile: $profile not found.");
        }

        $options['sql_file_name'] ??= $this->getSqlFileName();
        $options['secret'] = $this->backupPackage->getSecret();
        $options['root'] = WINDWALKER_ROOT;

        $dbOptions = $options['database'] ?? [];
        $connection = $dbOptions['connection'] ?? null;

        if ($connection) {
            $db = $this->databaseManager->get($connection);

            $dbOptions = $db->getOptions();
            $dbOptions = DatabaseManager::mergeDsnToOptions($dbOptions);
        }

        $options['database'] = $dbOptions;

        $runner = new BackupRunner($options);
        $runner->checkDbConnection();
        $runner->backup($backupOutput);

        return 0;
    }

    protected function getSqlFileName(): string
    {
        $appName = $this->app->getAppName();
        $time = now('Y-m-d-H-i-s');

        if ($appName) {
            return StrNormalize::toKebabCase($appName) . "-sql-backup-$time.sql";
        }

        return "site-sql-backup-$time.sql";
    }
}
