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

final class GenerateMeetupMarkdownCommand extends Command
{
    protected static $defaultName = 'meetup:generate-markdown';
    protected static $defaultDescription = 'Convert downloaded Meetup event JSON files into Markdown content.';

    public function __construct()
    {
        parent::__construct(self::$defaultName);
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'input-dir',
                'i',
                InputOption::VALUE_REQUIRED,
                'Directory containing Meetup event JSON files.'
            )
            ->addOption(
                'output-dir',
                'o',
                InputOption::VALUE_REQUIRED,
                'Directory where generated Markdown files will be written.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $inputDir = (string) $input->getOption('input-dir');
        $outputDir = (string) $input->getOption('output-dir');

        if ($inputDir === '') {
            $io->error('You must provide the input directory via --input-dir.');
            return Command::INVALID;
        }

        if ($outputDir === '') {
            $io->error('You must provide the output directory via --output-dir.');
            return Command::INVALID;
        }

        try {
            $io->section(sprintf('Generating Markdown files from %s into %s', $inputDir, $outputDir));

            Meetup::generate_event_markdown_files(
                $inputDir,
                $outputDir,
                static function (string $message) use ($io): void {
                    $io->writeln($message);
                }
            );

            $io->success('Markdown generation completed.');
            return Command::SUCCESS;
        } catch (Throwable $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }
    }
}
