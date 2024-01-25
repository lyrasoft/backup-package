<?php

declare(strict_types=1);

namespace Lyrasoft\Backup\Script;

use Lyrasoft\Backup\BackupCli;
use Lyrasoft\Backup\Service\BackupRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    'token',
    'Show backup token.'
)]
class CliTokenCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var BackupCli $app */
        $app = $this->getApplication();
        $options = $app->getOptions();

        $runner = new BackupRunner($options);
        $output->writeln($runner->token());

        return 0;
    }
}
