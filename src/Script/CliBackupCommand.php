<?php

declare(strict_types=1);

namespace Lyrasoft\Backup\Script;

use Lyrasoft\Backup\BackupCli;
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
    'backup',
    'Run backup.'
)]
class CliBackupCommand extends Command
{
    protected function configure()
    {
        $this->addOption(
            'output',
            'o',
            InputOption::VALUE_REQUIRED,
            'Save to path.',
            'php://stdout'
        );

        $this->addOption(
            'host',
            '',
            InputOption::VALUE_REQUIRED,
            'Database host.'
        );

        $this->addOption(
            'user',
            'u',
            InputOption::VALUE_REQUIRED,
            'Database user.'
        );

        $this->addOption(
            'pass',
            'p',
            InputOption::VALUE_REQUIRED,
            'Database password.'
        );

        $this->addOption(
            'db',
            '',
            InputOption::VALUE_REQUIRED,
            'Database name.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $backupOutput = $input->getOption('output');

        /** @var BackupCli $app */
        $app = $this->getApplication();
        $options = $app->getOptions();

        $dbOptions = [
            'host' => $input->getOption('host'),
            'user' => $input->getOption('user'),
            'pass' => $input->getOption('pass'),
            'dbname' => $input->getOption('db'),
        ];
        $dbOptions = array_filter($dbOptions);

        $options['database'] = array_merge($options['database'], $dbOptions);

        $runner = new BackupRunner($options);
        $runner->backup($backupOutput);

        return 0;
    }
}
