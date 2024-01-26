<?php

declare(strict_types=1);

namespace Lyrasoft\Backup\Command;

use Lyrasoft\Backup\BackupPackage;
use Lyrasoft\Backup\Service\BackupRunner;
use Lyrasoft\Backup\Service\PortalBackupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Windwalker\Console\Input\InputOption;
use Windwalker\Core\Application\ApplicationInterface;
use Windwalker\DI\Attributes\Inject;

#[AsCommand(
    'backup:register',
    'Register backup to server or show command.'
)]
class BackupRegisterCommand extends Command
{
    #[Inject]
    protected ApplicationInterface $app;

    #[Inject]
    protected BackupPackage $backupPackage;

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
            'show',
            's',
            InputOption::VALUE_NONE,
            'Show register command.',
        );

        $this->addOption(
            'url',
            'u',
            InputOption::VALUE_REQUIRED,
            'Site URL.',
        );

        $this->addOption(
            'title',
            't',
            InputOption::VALUE_REQUIRED,
            'Backup task title.',
        );
    }

    #[\ReturnTypeWillChange]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $profile = $input->getArgument('profile');
        $show = $input->getOption('show');

        $options = $this->app->config('backup.profiles.' . $profile);

        if ($options === null) {
            throw new \RuntimeException("Backup profile: $profile not found.");
        }

        $options['secret'] = $this->backupPackage->getSecret();

        /** @var QuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');
        $url = $input->getOption('url')
            ?: $questionHelper->ask($input, $output, new Question('Site URL: '));

        $runner = new BackupRunner($options);

        $url = rtrim((string) $url, '/') . '/backup';

        if ($show) {
            $output->writeln($runner->nas($this->app->getAppName(), $url));
        } else {
            $title = $this->app->getAppName()
                ?: $input->getOption('title')
                ?: $questionHelper->ask($input, $output, new Question('Backup Title: '));

            $authService = $this->app->service(PortalBackupService::class);
            $accessToken = $authService->auth($output);

            $data = $authService->register(
                $accessToken,
                [
                    'title' => $title,
                    'token' => $runner->token(),
                    'url' => $url
                ]
            );

            $output->writeln('');
            $output->writeln('');
            $output->writeln('Backup register SUCCESS.');

            if ($data['link'] ?? null) {
                $output->writeln('Please open this URL to configure it:');
                $output->writeln("<info>{$data['link']}</info>");
            }
        }

        return 0;
    }
}
