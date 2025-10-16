<?php

declare(strict_types=1);

namespace PHPValencia\Cli;

use PHPValencia\Meetup;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

final class DownloadMeetupEventsCommand extends Command
{
    protected static $defaultName = 'meetup:download-events';
    protected static $defaultDescription = 'Download event data from Meetup and store JSON snapshots.';

    public function __construct()
    {
        parent::__construct(self::$defaultName);
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'events-file',
                'e',
                InputOption::VALUE_REQUIRED,
                'Path to the JSON file that lists Meetup event identifiers.'
            )
            ->addOption(
                'output-dir',
                'o',
                InputOption::VALUE_REQUIRED,
                'Directory where downloaded event JSON files will be saved.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $eventsFile = (string) $input->getOption('events-file');
        $outputDir = (string) $input->getOption('output-dir');

        if ($eventsFile === '') {
            $io->error('You must provide the path to the events JSON file via --events-file.');
            return Command::INVALID;
        }

        if ($outputDir === '') {
            $io->error('You must provide the output directory via --output-dir.');
            return Command::INVALID;
        }

        try {
            $events = Meetup::load_event_ids($eventsFile);
            $io->section(sprintf('Downloading %d events into %s', count($events), $outputDir));

            Meetup::download_meetup_events(
                $events,
                $outputDir,
                static function (string $message) use ($io): void {
                    $io->writeln($message);
                }
            );

            $io->success('Event download completed.');
            return Command::SUCCESS;
        } catch (Throwable $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }
    }
}
