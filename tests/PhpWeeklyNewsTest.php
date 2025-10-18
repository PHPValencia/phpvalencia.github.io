<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use PHPValencia\PhpWeeklyNews;

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
            null
        );

        $this->assertNotNull($filePath);
        $this->assertFileExists($filePath);
        $this->assertStringContainsString('Boletín mensual', file_get_contents($filePath));

        array_map('unlink', (array) glob($outputDir . '/*'));
        rmdir($outputDir);
    }
}
