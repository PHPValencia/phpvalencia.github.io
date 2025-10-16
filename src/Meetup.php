<?php

namespace PHPValencia;

use DateTime;
use GuzzleHttp\Client;
use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

class Meetup
{
    public static function load_event_ids(string $filePath): array
    {
        $json = file_get_contents($filePath);

        if ($json === false) {
            throw new RuntimeException("Failed to read events file: {$filePath}");
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw new RuntimeException('Events file did not decode to an array.');
        }

        return $data;
    }

    public static function fetch_url(string $url): string
    {
        $client = new Client([
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible;)',
                'Accept' => 'text/html,application/xhtml+xml',
            ],
        ]);

        $response = $client->get($url);
        $html = $response->getBody()->getContents();

        return $html;
    }

    public static function extract_event_data_json(string $html): ?array
    {
        $crawler = new Crawler($html);
        $script = $crawler->filter('script#__NEXT_DATA__');

        if ($script->count() === 0) {
            throw new RuntimeException('Could not find __NEXT_DATA__ script tag.');
        }

        $jsonText = $script->text();
        $data = json_decode($jsonText, true, 512, JSON_THROW_ON_ERROR);

        return $data["props"]["pageProps"]["event"];
    }

    public static function save_json(array $data, string $filePath): void
    {
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            throw new RuntimeException('Failed to encode JSON: ' . json_last_error_msg());
        }

        if (file_put_contents($filePath, $encoded) === false) {
            throw new RuntimeException("Failed to write file: $filePath");
        }

        echo "JSON saved to: $filePath\n";
    }

    public static function normalize_event_date(string $dateTime): string
    {
        $dt = new DateTime($dateTime);
        return $dt->format('Y-m-d');
    }

    public static function download_meetup_events(array $events, string $targetDir): void
    {
        $normalizedTargetDir = rtrim($targetDir, DIRECTORY_SEPARATOR);

        foreach ($events as $id) {
            try {
                $url = "https://www.meetup.com/php-valencia/events/{$id}/";
                $html = self::fetch_url($url);
                $data = self::extract_event_data_json($html);
                $eventDate = self::normalize_event_date($data["dateTime"]);
                $targetFile = $normalizedTargetDir . "/event_{$eventDate}_{$id}.json";
                self::save_json($data, $targetFile);
            } catch (Throwable $e) {
                echo "Error: " . $e->getMessage() . "\n";
            }

            sleep(1);
        }
    }

    public static function event_address(array $venue): string
    {
        return "{$venue['address']}, {$venue['city']}";
    }

    public static function convert_event_to_markdown(array $data): string
    {
        $eventDate = new DateTime($data["dateTime"]);

        $title = $data["title"];
        $date = $eventDate->format('Y-m-d');
        $start = $eventDate->format('H:i');
        $address = self::event_address($data["venue"]);
        $meetup = $data["eventUrl"];
        $description = $data["description"];

        $content = sprintf('---
title: "%s"
date: %s
start: "%s"
address: "%s"
meetup: "%s"
section: content
---

%s
', $title, $date, $start, $address, $meetup, $description);

        return $content;
    }

    public static function load_event_file(string $filePath): array
    {
        $json = file_get_contents($filePath);

        if ($json === false) {
            throw new RuntimeException("Failed to read event file: {$filePath}");
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw new RuntimeException('Event file did not decode to an array.');
        }

        return $data;
    }

    public static function generate_event_markdown_files(string $inputDir, string $outputDir): void
    {
        $normalizedInputDir = rtrim($inputDir, DIRECTORY_SEPARATOR);
        $normalizedOutputDir = rtrim($outputDir, DIRECTORY_SEPARATOR);
        $files = glob($normalizedInputDir . '/*.json');

        foreach ($files as $file) {
            echo "Processing $file...\n";

            $data = self::load_event_file($file);
            $markdown = self::convert_event_to_markdown($data);
            $markdown = rtrim($markdown, "\n") . "\n";
            $eventDate = self::normalize_event_date($data["dateTime"]);
            $outputFile = $normalizedOutputDir . "/{$eventDate}_{$data['id']}.md";
            file_put_contents($outputFile, $markdown);

            echo "Created $outputFile\n";
        }

        echo "All done.\n";
    }
}
