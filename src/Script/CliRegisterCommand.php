<?php

declare(strict_types=1);

namespace Lyrasoft\Backup\Script;

use Lyrasoft\Backup\BackupCli;
use Lyrasoft\Backup\Service\BackupRunner;
use Lyrasoft\Backup\Service\PortalBackupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Windwalker\Console\Input\InputOption;

#[AsCommand(
    'register',
    'Register backup to server or show command.'
)]
class CliRegisterCommand extends Command
{
    #[\ReturnTypeWillChange]
    protected function configure()
    {
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
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $show = $input->getOption('show');

        /** @var BackupCli $app */
        $app = $this->getApplication();
        $options = $app->getOptions();

        /** @var QuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');
        $url = $input->getOption('url')
            ?: $questionHelper->ask($input, $output, new Question('Site URL: '));

        $runner = new BackupRunner($options);

        if ($show) {
            $url = rtrim($url, '/') . '/backup.php';
            $output->writeln($runner->nas($options['name'], $url));
        } else {
            $title = $options['name']
                ?: $input->getOption('title')
                    ?: $questionHelper->ask($input, $output, new Question('Backup Title: '));

            $authService = new PortalBackupService();
            $accessToken = $authService->auth($output);

            $data = $authService->register(
                $accessToken,
                [
                    'title' => $title,
                    'token' => $runner->token(),
                    'url' => rtrim($url, '/') . '/backup.php',
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
