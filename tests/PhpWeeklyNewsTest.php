<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use PHPValencia\PhpWeeklyNews;
use Symfony\Component\DomCrawler\Crawler;

final class PhpWeeklyNewsTest extends TestCase
{
    private string $fixture;
    private string $archiveFixture;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(new CarbonImmutable('2025-10-20'));

        $this->fixture = file_get_contents(__DIR__ . '/fixtures/phpweekly_sample.html');
        $this->assertNotFalse($this->fixture, 'Fixture content could not be loaded.');

        $this->archiveFixture = file_get_contents(__DIR__ . '/fixtures/archive_sample.html');
        $this->assertNotFalse($this->archiveFixture, 'Archive fixture content could not be loaded.');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function testExtractIssueDateParsesExpectedValue(): void
    {
        $date = PhpWeeklyNews::extract_issue_date($this->fixture);

        $this->assertSame('2025-10-17', $date->format('Y-m-d'));
    }

    public function testExtractEntriesParsesSectionsAndEntries(): void
    {
        $entries = PhpWeeklyNews::extract_entries($this->fixture);

        $this->assertArrayHasKey('Articles', $entries);
        $this->assertArrayHasKey('Tutorials and Talks', $entries);

        $this->assertCount(2, $entries['Articles']);
        $this->assertCount(1, $entries['Tutorials and Talks']);

        $firstArticle = $entries['Articles'][0];
        $this->assertSame('First article title', $firstArticle['title']);
        $this->assertSame('https://example.com/article-1', $firstArticle['url']);
        $this->assertStringContainsString('first article description', mb_strtolower($firstArticle['description']));
    }

    public function testBuildMarkdownGeneratesFrontMatterAndContent(): void
    {
        $issueDate = new CarbonImmutable('2025-10-17');
        $publicationDate = $issueDate->endOfMonth();

        $entries = [
            'Articles' => [
                [
                    'title' => 'Sample Article',
                    'url' => 'https://example.com/article',
                    'description' => 'Detailed overview about PHP.',
                ],
            ],
            'News and Announcements' => [
                [
                    'title' => 'Important Announcement',
                    'url' => 'https://example.com/news',
                    'description' => 'Something happened in the PHP community.',
                ],
            ],
        ];

        $markdown = PhpWeeklyNews::build_markdown($issueDate, $publicationDate, $entries);

        $this->assertStringContainsString('title: "Boletín mensual · Octubre 2025"', $markdown);
        $this->assertStringContainsString('date: 2025-10-31', $markdown);
        $this->assertStringContainsString('### Artículos destacados', $markdown);
        $this->assertStringContainsString('- [Sample Article](https://example.com/article). Resumen (inglés): Detailed overview about PHP.', $markdown);
        $this->assertStringNotContainsString('Selección de noticias PHP', $markdown);
        $this->assertStringNotContainsString('Este resumen mensual destaca', $markdown);
    }

    public function testExportCreatesMarkdownFileUsingProvidedClient(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], $this->archiveFixture),
            new Response(200, [], $this->fixture),
        ]);
        $handlerStack = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $outputDir = sys_get_temp_dir() . '/phpweekly-news-' . uniqid();
        $this->assertTrue(mkdir($outputDir));

        $filePath = PhpWeeklyNews::export(
            'https://www.phpweekly.com/archive.html',
            $outputDir,
            $client,
            new CarbonImmutable('2025-10-01'),
            null
        );

        $this->assertNotNull($filePath);
        $this->assertFileExists($filePath);
        $this->assertStringContainsString('Boletín mensual', file_get_contents($filePath));

        array_map('unlink', (array) glob($outputDir . '/*'));
        rmdir($outputDir);
    }

    public function testExtractMonthIssueLinksFiltersByMonthAndResolvesUrls(): void
    {
        $issues = PhpWeeklyNews::extract_month_issue_links(
            $this->archiveFixture,
            'https://www.phpweekly.com/archive.html',
            new CarbonImmutable('2025-10-01')
        );

        $this->assertCount(1, $issues);
        $this->assertSame('2025-10-17', $issues[0]['date']->format('Y-m-d'));
        $this->assertSame('https://www.phpweekly.com/archive/2025-10-17.html', $issues[0]['url']);
    }

    public function testParseSectionEntriesExtractsLinksWithinParagraphs(): void
    {
        $crawler = new Crawler($this->fixture);
        $node = $crawler->filter('td.bodyContent')->first()->getNode(0);
        $this->assertInstanceOf(\DOMElement::class, $node);

        $entries = PhpWeeklyNews::parse_section_entries($node);
        $this->assertNotEmpty($entries);
        $this->assertSame('First article title', $entries[0]['title']);
    }

    public function testParseSectionSiblingsDetectsLinksOutsideParagraphs(): void
    {
        $html = <<<HTML
        <td class="bodyContent">
            <h2><span style="font-size:20px">Custom</span></h2>
            <a href="https://example.com/one">First</a> Description one.
            <br>
            <a href="https://example.com/two">Second</a> Description two.
        </td>
        HTML;

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<table><tr>' . $html . '</tr></table>');
        libxml_clear_errors();
        $node = $dom->getElementsByTagName('td')->item(0);
        $this->assertInstanceOf(\DOMElement::class, $node);

        $entries = PhpWeeklyNews::parse_section_siblings($node);
        $this->assertCount(2, $entries);
        $this->assertSame('https://example.com/two', $entries[1]['url']);
    }

    public function testNormalizeTextTrimsAndDecodesEntities(): void
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('<div>Texto&nbsp;limpio</div>');
        $node = $dom->getElementsByTagName('div')->item(0)->firstChild;
        $this->assertSame('Texto limpio', PhpWeeklyNews::normalize_text($node));
    }

    public function testNormalizeWhitespaceReplacesNonBreakingSpaces(): void
    {
        $this->assertSame('Texto limpio', PhpWeeklyNews::normalize_whitespace(" Texto\u{A0}limpio "));
    }

    public function testSummarizeEnglishDescriptionAddsPrefixAndEllipsis(): void
    {
        $text = str_repeat('Word ', 50);
        $summary = PhpWeeklyNews::summarize_english_description($text);

        $this->assertStringStartsWith('Resumen (inglés):', $summary);
        $this->assertStringEndsWith('…', $summary);
    }

    public function testFormatMonthYearReturnsSpanishMonth(): void
    {
        $label = PhpWeeklyNews::format_month_year(new CarbonImmutable('2025-02-10'));
        $this->assertSame('Febrero 2025', $label);
    }

    public function testEnsureTrailingNewlineAppendsWhenMissing(): void
    {
        $result = PhpWeeklyNews::ensure_trailing_newline("linea");
        $this->assertSame("linea\n", $result);
    }

    public function testResolveUrlBuildsAbsolutePaths(): void
    {
        $resolved = PhpWeeklyNews::resolve_url('https://www.phpweekly.com/archive.html', '/archive/test.html');
        $this->assertSame('https://www.phpweekly.com/archive/test.html', $resolved);
    }

    public function testExtractBaseUrlReturnsSchemeHostAndPort(): void
    {
        $base = PhpWeeklyNews::extract_base_url('https://www.phpweekly.com:443/archive.html');
        $this->assertSame('https://www.phpweekly.com:443', $base);
    }

    public function testEnsureDirectoryCreatesMissingFolders(): void
    {
        $dir = sys_get_temp_dir() . '/news-dir-' . uniqid();
        $this->assertDirectoryDoesNotExist($dir);
        PhpWeeklyNews::ensure_directory($dir);
        $this->assertDirectoryExists($dir);
        rmdir($dir);
    }

    public function testBuildTargetPathUsesPublicationMonth(): void
    {
        $path = PhpWeeklyNews::build_target_path('/tmp/news', new CarbonImmutable('2025-10-31'));
        $this->assertSame('/tmp/news/2025-10-boletin-mensual.md', $path);
    }

    public function testFetchIssueReturnsResponseBody(): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], 'HTML-CONTENT'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mockHandler)]);

        $body = PhpWeeklyNews::fetch_issue($client, 'https://example.com/issue.html');
        $this->assertSame('HTML-CONTENT', $body);
    }

    public function testLogWritesMessagesWhenLoggerProvided(): void
    {
        $messages = [];
        PhpWeeklyNews::log(static function (string $message) use (&$messages): void {
            $messages[] = $message;
        }, 'Mensaje de prueba');

        $this->assertSame(['Mensaje de prueba'], $messages);
    }
}
