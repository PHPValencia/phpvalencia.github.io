<?php

declare(strict_types=1);

namespace PHPValencia\Cli;

use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use PHPValencia\PhpWeeklyNews;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

final class GenerateNewsMarkdownCommand extends Command
{
    protected static $defaultName = 'news:generate-monthly';
    protected static $defaultDescription = 'Genera la entrada mensual del boletín a partir de PHP Weekly.';

    public function __construct()
    {
        parent::__construct(self::$defaultName);
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'output-dir',
                'o',
                InputOption::VALUE_REQUIRED,
                'Directorio donde se guardarán los boletines generados.',
                'source/_news'
            )
            ->addOption(
                'archive-url',
                'a',
                InputOption::VALUE_REQUIRED,
                'URL del archivo de PHP Weekly desde el que se extraerán las publicaciones del mes.',
                'https://www.phpweekly.com/archive.html'
            )
            ->addOption(
                'month',
                'm',
                InputOption::VALUE_REQUIRED,
                'Mes a procesar en formato YYYY-MM (por defecto el mes actual en UTC).'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $outputDir = (string) $input->getOption('output-dir');
        $archiveUrl = (string) $input->getOption('archive-url');
        $monthOption = (string) $input->getOption('month');

        if ($outputDir === '') {
            $io->error('Debes indicar un directorio de salida mediante --output-dir.');
            return Command::INVALID;
        }

        if ($archiveUrl === '') {
            $io->error('Debes indicar la URL del archivo mediante --archive-url.');
            return Command::INVALID;
        }

        $referenceMonth = null;

        if ($monthOption !== '') {
            $monthOption = trim($monthOption);

            if (!preg_match('/^\d{4}-\d{2}$/', $monthOption)) {
                $io->error('El parámetro --month debe tener formato YYYY-MM (por ejemplo 2025-10).');
                return Command::INVALID;
            }

            $parsed = CarbonImmutable::createFromFormat('Y-m', $monthOption, 'UTC');

            if ($parsed === false) {
                $io->error('No se pudo interpretar el mes proporcionado.');
                return Command::INVALID;
            }

            $referenceMonth = $parsed->startOfMonth();
        }

        try {
            $client = new Client([
                'timeout' => 15,
                'headers' => [
                    'User-Agent' => 'PHPValenciaBot/1.0 (+https://phpvalencia.es)',
                    'Accept' => 'text/html,application/xhtml+xml',
                ],
            ]);

            $result = PhpWeeklyNews::export(
                $archiveUrl,
                $outputDir,
                $client,
                $referenceMonth,
                static function (string $message) use ($io): void {
                    $io->writeln($message);
                }
            );

            if ($result === null) {
                $io->success('No se generó ningún fichero nuevo (ya existía el boletín del mes).');
            } else {
                $io->success(sprintf('Boletín generado en %s.', $result));
            }

            return Command::SUCCESS;
        } catch (Throwable $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }
    }
}
