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
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $backupOutput = $input->getOption('output');

        /** @var BackupCli $app */
        $app = $this->getApplication();
        $options = $app->getOptions();

        $runner = new BackupRunner($options);
        $runner->backup($backupOutput);

        return 0;
    }
}
