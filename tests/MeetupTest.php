<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPValencia\Meetup;

class MeetupTest extends TestCase
{
    public function testLoadEventIdsReturnsDecodedArray(): void
    {
        $filePath = __DIR__ . '/../meetup_events.json';
        $ids = Meetup::load_event_ids($filePath);

        $this->assertIsArray($ids);
        $this->assertNotEmpty($ids);
    }

    public function testNormalizeEventDateFormatsAsExpected(): void
    {
        $date = Meetup::normalize_event_date('2024-03-05T18:30:00Z');

        $this->assertSame('2024-03-05', $date);
    }

    public function testEventAddressConcatenatesFields(): void
    {
        $address = Meetup::event_address(['address' => '123 Main St', 'city' => 'Valencia']);

        $this->assertSame('123 Main St, Valencia', $address);
    }

    public function testConvertEventToMarkdownProducesFrontMatter(): void
    {
        $markdown = Meetup::convert_event_to_markdown([
            'dateTime' => '2024-04-01T19:00:00Z',
            'title' => 'Sample Talk',
            'venue' => ['address' => '123 Main St', 'city' => 'Valencia'],
            'eventUrl' => 'https://example.com/event',
            'description' => '<p>Event description</p>',
            'id' => '789',
        ]);

        $this->assertStringContainsString('title: "Sample Talk"', $markdown);
        $this->assertStringContainsString('date: 2024-04-01', $markdown);
        $this->assertStringContainsString('start: "19:00"', $markdown);
        $this->assertStringContainsString('address: "123 Main St, Valencia"', $markdown);
        $this->assertStringContainsString('meetup: "https://example.com/event"', $markdown);
        $this->assertStringContainsString('<p>Event description</p>', $markdown);
    }

    public function testGenerateEventMarkdownFilesCreatesMarkdown(): void
    {
        $realFiles = glob(__DIR__ . '/../meetup_events_data/*.json');
        $this->assertNotEmpty($realFiles, 'Expected at least one real event JSON file.');

        $sourceFile = $realFiles[0];
        $eventData = Meetup::load_event_file($sourceFile);

        $inputDir = sys_get_temp_dir() . '/meetup-input-' . uniqid();
        $outputDir = sys_get_temp_dir() . '/meetup-output-' . uniqid();

        mkdir($inputDir);
        mkdir($outputDir);

        $inputFile = $inputDir . '/' . basename($sourceFile);

        ob_start();
        Meetup::save_json($eventData, $inputFile);
        ob_end_clean();

        ob_start();
        Meetup::generate_event_markdown_files($inputDir, $outputDir);
        ob_end_clean();

        $eventDate = Meetup::normalize_event_date($eventData['dateTime']);
        $expectedFile = rtrim($outputDir, DIRECTORY_SEPARATOR) . "/{$eventDate}_{$eventData['id']}.md";
        $this->assertFileExists($expectedFile);

        $markdown = file_get_contents($expectedFile);
        $this->assertStringContainsString('title: "' . $eventData['title'] . '"', $markdown);
        $this->assertStringContainsString('meetup: "' . $eventData['eventUrl'] . '"', $markdown);

        array_map('unlink', (array) glob($inputDir . '/*'));
        array_map('unlink', (array) glob($outputDir . '/*'));
        rmdir($inputDir);
        rmdir($outputDir);
    }
}
