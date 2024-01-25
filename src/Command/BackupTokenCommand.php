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
    'backup:token',
    'Show backup token.'
)]
class BackupTokenCommand extends Command
{
    #[Inject]
    protected ApplicationInterface $app;

    #[\ReturnTypeWillChange]
    protected function configure()
    {
        $this->addArgument(
            'profile',
            InputArgument::OPTIONAL,
            'The backup setting profile name.',
            'default'
        );
    }

    #[\ReturnTypeWillChange]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $profile = $input->getArgument('profile');

        $options = $this->app->config('backup.profiles.' . $profile);

        if ($options === null) {
            throw new \RuntimeException("Backup profile: $profile not found.");
        }

        $options['secret'] = $this->app->getSecret();

        $runner = new BackupRunner($options);
        $output->writeln($runner->token());

        return 0;
    }
}
