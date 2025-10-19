<?php

declare(strict_types=1);

namespace PHPValencia;

use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;

final class PhpWeeklyNews
{
    /** @var array<string, array{heading: string}> */
    private const CATEGORY_METADATA = [
        'Articles' => [
            'heading' => 'Artículos destacados',
        ],
        'Tutorials and Talks' => [
            'heading' => 'Tutoriales y charlas',
        ],
        'News and Announcements' => [
            'heading' => 'Noticias y lanzamientos',
        ],
        'Podcasts and Vlogs' => [
            'heading' => 'Podcasts y vídeos',
        ],
        'Interesting Projects, Tools and Libraries' => [
            'heading' => 'Proyectos, herramientas y librerías',
        ],
    ];

    /**
     * @param callable(string):void|null $logger
     */
    public static function export(
        string $archiveUrl,
        string $outputDir,
        Client $client,
        ?CarbonImmutable $referenceMonth = null,
        ?callable $logger = null
    ): ?string {
        self::log($logger, sprintf('Descargando índice del archivo de PHP Weekly: %s', $archiveUrl));

        $archiveHtml = self::fetch_issue($client, $archiveUrl);
        $referenceDate = ($referenceMonth ?? CarbonImmutable::now('UTC'))->startOfMonth();
        $issues = self::extract_month_issue_links($archiveHtml, $archiveUrl, $referenceDate);

        if ($issues === []) {
            throw new RuntimeException('No se han encontrado publicaciones para el mes actual en el archivo.');
        }

        $aggregatedEntries = [];
        $seenLinks = [];

        foreach ($issues as $issue) {
            self::log(
                $logger,
                sprintf('Procesando boletín del %s (%s)', $issue['date']->format('Y-m-d'), $issue['url'])
            );

            $issueHtml = self::fetch_issue($client, $issue['url']);
            $entries = self::extract_entries($issueHtml);

            foreach ($entries as $category => $items) {
                foreach ($items as $entry) {
                    $url = trim($entry['url']);

                    if ($url === '') {
                        continue;
                    }

                    $key = strtolower($url);

                    if (!isset($aggregatedEntries[$category])) {
                        $aggregatedEntries[$category] = [];
                        $seenLinks[$category] = [];
                    }

                    if (isset($seenLinks[$category][$key])) {
                        continue;
                    }

                    $aggregatedEntries[$category][] = $entry;
                    $seenLinks[$category][$key] = true;
                }
            }
        }

        if ($aggregatedEntries === []) {
            throw new RuntimeException('No se han podido consolidar entradas del mes actual.');
        }

        $publicationDate = $referenceDate->endOfMonth();
        $filePath = self::build_target_path($outputDir, $publicationDate);

        if (file_exists($filePath)) {
            self::log($logger, sprintf('El boletín %s ya existe y no se sobrescribirá.', basename($filePath)));
            return null;
        }

        $markdown = self::build_markdown($referenceDate, $publicationDate, $aggregatedEntries);
        self::ensure_directory(dirname($filePath));

        if (file_put_contents($filePath, $markdown) === false) {
            throw new RuntimeException(sprintf('No se pudo escribir el fichero %s.', $filePath));
        }

        self::log($logger, sprintf('Boletín generado en %s.', $filePath));

        return $filePath;
    }

    public static function extract_issue_date(string $html): CarbonImmutable
    {
        $crawler = new Crawler($html);
        $heading = $crawler->filter('div.cc-f-para h2.talk')->first();

        if ($heading->count() === 0) {
            throw new RuntimeException('No se ha encontrado la fecha de la edición de PHP Weekly.');
        }

        $rawDate = trim($heading->text());
        $date = CarbonImmutable::createFromFormat('F j, Y', $rawDate, 'UTC');

        if ($date === false) {
            $date = CarbonImmutable::createFromFormat('F d, Y', $rawDate, 'UTC');
        }

        if ($date === false) {
            throw new RuntimeException(sprintf('Formato de fecha inesperado en PHP Weekly: %s', $rawDate));
        }

        return $date;
    }

    /**
     * @return array<string, list<array{title: string, url: string, description: string}>>
     */
    public static function extract_entries(string $html): array
    {
        $crawler = new Crawler($html);
        $sections = [];

        foreach ($crawler->filter('td.bodyContent') as $sectionNode) {
            $sectionCrawler = new Crawler($sectionNode);
            if ($sectionCrawler->filter('h2 span')->count() === 0) {
                continue;
            }

            $categoryName = trim($sectionCrawler->filter('h2 span')->text());

            if (!isset(self::CATEGORY_METADATA[$categoryName])) {
                continue;
            }

            $entries = self::parse_section_entries($sectionNode);
            if ($entries !== []) {
                $sections[$categoryName] = $entries;
            }
        }

        return $sections;
    }

    /**
     * @param array<string, list<array{title: string, url: string, description: string}>> $entries
     */
    public static function build_markdown(
        CarbonImmutable $referenceDate,
        CarbonImmutable $publicationDate,
        array $entries
    ): string {
        $monthLabel = self::format_month_year($referenceDate);

        $frontMatter = [
            '---',
            sprintf('title: "Boletín mensual · %s"', $monthLabel),
            sprintf('date: %s', $publicationDate->format('Y-m-d')),
            'extends: _layouts.master',
            'section: contents',
            '---',
            '',
        ];

        $sections = [];

        foreach (self::CATEGORY_METADATA as $category => $metadata) {
            if (!isset($entries[$category]) || $entries[$category] === []) {
                continue;
            }

            $sections[] = sprintf('### %s', $metadata['heading']);
            $sections[] = '';

            foreach ($entries[$category] as $entry) {
                $summary = self::summarize_english_description($entry['description']);
                if ($summary === '') {
                    $summary = 'Resumen no disponible.';
                }

                $sections[] = sprintf('- [%s](%s). %s', $entry['title'], $entry['url'], $summary);
            }

            $sections[] = '';
        }

        return self::ensure_trailing_newline(
            implode(PHP_EOL, array_merge($frontMatter, $sections))
        );
    }

    /**
     * @return list<array{date: CarbonImmutable, url: string}>
     */
    public static function extract_month_issue_links(
        string $archiveHtml,
        string $archiveUrl,
        CarbonImmutable $referenceDate
    ): array {
        $crawler = new Crawler($archiveHtml);
        $targetHeading = $referenceDate->format('F Y');
        $issues = [];
        $seen = [];
        $collect = false;

        foreach ($crawler->filter('div.archive-d') as $nodeElement) {
            $node = new Crawler($nodeElement);

            if ($node->filter('h2.archive-h')->count() > 0) {
                $heading = trim($node->filter('h2.archive-h')->text());
                $collect = strcasecmp($heading, $targetHeading) === 0;
                continue;
            }

            if (!$collect) {
                continue;
            }

            $linkNode = $node->filter('h3 a');

            if ($linkNode->count() === 0) {
                continue;
            }

            $href = trim((string) $linkNode->attr('href'));
            $label = trim($linkNode->text());

            if ($href === '' || $label === '') {
                continue;
            }

            $issueDate = CarbonImmutable::createFromFormat('d F, Y', $label, 'UTC');

            if ($issueDate === false) {
                $issueDate = CarbonImmutable::createFromFormat('d F Y', $label, 'UTC');
            }

            if ($issueDate === false) {
                continue;
            }

            if ($issueDate->format('Y-m') !== $referenceDate->format('Y-m')) {
                continue;
            }

            $absoluteUrl = self::resolve_url($archiveUrl, $href);

            if (isset($seen[$absoluteUrl])) {
                continue;
            }

            $seen[$absoluteUrl] = true;
            $issues[] = [
                'date' => $issueDate,
                'url' => $absoluteUrl,
            ];
        }

        return $issues;
    }

    public static function parse_section_entries(\DOMElement $sectionNode): array
    {
        $entries = [];
        $paragraphs = $sectionNode->getElementsByTagName('p');

        foreach ($paragraphs as $paragraph) {
            $current = null;
            /** @var \DOMNode|null $node */
            for ($node = $paragraph->firstChild; $node !== null; $node = $node->nextSibling) {
                if ($node instanceof \DOMElement && $node->tagName === 'a') {
                    if ($current !== null && $current['url'] !== '') {
                        $entries[] = $current;
                    }

                    $current = [
                        'title' => trim($node->textContent ?? ''),
                        'url' => trim($node->getAttribute('href')),
                        'description' => '',
                    ];
                } elseif ($current !== null) {
                    $text = self::normalize_text($node);
                    if ($text !== '') {
                        if ($current['description'] !== '') {
                            $current['description'] .= ' ';
                        }
                        $current['description'] .= $text;
                    }
                }
            }

            if ($current !== null && $current['url'] !== '') {
                $entries[] = $current;
            }
        }

        return $entries !== [] ? $entries : self::parse_section_siblings($sectionNode);
    }

    public static function parse_section_siblings(\DOMElement $sectionNode): array
    {
        $entries = [];
        $current = null;
        $afterHeading = false;

        for ($node = $sectionNode->firstChild; $node !== null; $node = $node->nextSibling) {
            if ($node instanceof \DOMElement && $node->tagName === 'h2') {
                $afterHeading = true;
                $current = null;
                continue;
            }

            if (!$afterHeading) {
                continue;
            }

            if ($node instanceof \DOMElement && $node->tagName === 'a') {
                if ($current !== null && $current['url'] !== '') {
                    $entries[] = $current;
                }

                $current = [
                    'title' => trim($node->textContent ?? ''),
                    'url' => trim($node->getAttribute('href')),
                    'description' => '',
                ];
                continue;
            }

            if ($current === null) {
                continue;
            }

            $text = self::normalize_text($node);
            if ($text === '') {
                continue;
            }

            if ($current['description'] !== '') {
                $current['description'] .= ' ';
            }

            $current['description'] .= $text;
        }

        if ($current !== null && $current['url'] !== '') {
            $entries[] = $current;
        }

        return $entries;
    }

    public static function fetch_issue(Client $client, string $sourceUrl): string
    {
        try {
            $response = $client->get($sourceUrl);
        } catch (GuzzleException $exception) {
            throw new RuntimeException(
                'No se pudo descargar PHP Weekly: ' . $exception->getMessage(),
                0,
                $exception
            );
        }

        $body = $response->getBody()->getContents();

        if ($body === '') {
            throw new RuntimeException('PHP Weekly devolvió una respuesta vacía.');
        }

        return $body;
    }

    public static function build_target_path(string $outputDir, CarbonImmutable $publicationDate): string
    {
        $normalizedDir = rtrim($outputDir, DIRECTORY_SEPARATOR);
        $filename = sprintf('%s-boletin-mensual.md', $publicationDate->format('Y-m'));

        return $normalizedDir . DIRECTORY_SEPARATOR . $filename;
    }

    public static function ensure_directory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('No se pudo crear el directorio %s.', $directory));
        }
    }

    public static function normalize_text(\DOMNode $node): string
    {
        if ($node instanceof \DOMText) {
            return self::normalize_whitespace(html_entity_decode($node->wholeText, ENT_QUOTES | ENT_HTML5));
        }

        if ($node instanceof \DOMElement && $node->tagName !== 'br') {
            return self::normalize_whitespace(html_entity_decode($node->textContent ?? '', ENT_QUOTES | ENT_HTML5));
        }

        return '';
    }

    public static function normalize_whitespace(string $value): string
    {
        $value = str_replace("\u{A0}", ' ', $value);
        return trim($value);
    }

    public static function summarize_english_description(string $description): string
    {
        $clean = preg_replace('/\s+/u', ' ', trim(html_entity_decode($description, ENT_QUOTES | ENT_HTML5))) ?? '';

        if ($clean === '') {
            return '';
        }

        $maxLength = 180;
        if (mb_strlen($clean) > $maxLength) {
            $clean = mb_substr($clean, 0, $maxLength - 1) . '…';
        }

        return 'Resumen (inglés): ' . $clean;
    }

    public static function format_month_year(CarbonImmutable $date): string
    {
        $months = [
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre',
        ];

        $month = $months[(int) $date->format('n')] ?? '';

        return sprintf('%s %s', self::capitalize($month), $date->format('Y'));
    }

    public static function capitalize(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    public static function ensure_trailing_newline(string $content): string
    {
        return rtrim($content, "\n") . "\n";
    }

    public static function resolve_url(string $baseUrl, string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $base = rtrim(self::extract_base_url($baseUrl), '/');
        $relative = '/' . ltrim($path, '/');

        return $base . $relative;
    }

    public static function extract_base_url(string $url): string
    {
        $components = parse_url($url);

        if (!is_array($components)) {
            return $url;
        }

        $scheme = $components['scheme'] ?? 'https';
        $host = $components['host'] ?? '';
        $port = isset($components['port']) ? ':' . $components['port'] : '';

        return $scheme . '://' . $host . $port;
    }

    /**
     * @param callable(string):void|null $logger
     */
    public static function log(?callable $logger, string $message): void
    {
        if ($logger !== null) {
            $logger($message);
        }
    }
}
