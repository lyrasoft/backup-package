<?php

declare(strict_types=1);

namespace Lyrasoft\Backup\Command;

use Lyrasoft\Backup\Service\BackupRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Windwalker\Core\Application\ApplicationInterface;
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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $profile = $input->getArgument('profile');
        $backupOutput = $input->getOption('output');

        $options = $this->app->config('backup.profiles.' . $profile);

        if ($options === null) {
            throw new \RuntimeException("Backup profile: $profile not found.");
        }

        $options['sql_file_name'] ??= $this->getSqlFileName();
        $options['secret'] = $this->app->getSecret();
        $options['root'] = WINDWALKER_ROOT;

        $runner = new BackupRunner($options);
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
