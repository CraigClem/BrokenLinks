<?php

namespace craigclement\craftbrokenlinks\services;

use Craft;
use GuzzleHttp\Client;
use yii\base\Component;

class BrokenLinksService extends Component
{
    public function crawlSite(string $baseUrl): array
    {
        $client = new Client(['timeout' => 5]); // Set a timeout for requests
        $brokenLinks = [];
        $visitedUrls = [];

        Craft::info("Starting crawl for base URL: $baseUrl", __METHOD__);

        try {
            // Fetch all entries from Craft CMS and eager load fields
            $entries = Craft::$app->elements->createElementQuery(\craft\elements\Entry::class)
                ->with(['*'])
                ->all();

            Craft::info("Found " . count($entries) . " entries to crawl.", __METHOD__);

            foreach ($entries as $entry) {
                $url = $entry->getUrl();

                if (!$url || in_array($url, $visitedUrls)) {
                    Craft::info("Skipping entry ID: {$entry->id} - URL: $url", __METHOD__);
                    continue; 
                }

                $visitedUrls[] = $url;

                // Crawl the entry's page
                $this->crawlPage($client, $url, $brokenLinks, $visitedUrls, $entry);
            }

            return $brokenLinks;
        } catch (\Throwable $e) {
            Craft::error("Error during crawl: " . $e->getMessage(), __METHOD__);
            throw $e;
        }
    }

    private function crawlPage(Client $client, string $url, array &$brokenLinks, array &$visitedUrls, $entry = null): void
    {
        try {
            $response = $client->get($url);
            $html = $response->getBody()->getContents();

            // Extract anchor tags with their text
            preg_match_all('/<a\s+(?:[^>]*?\s+)?href="([^"]*)".*?>(.*?)<\/a>/is', $html, $matches);
            $urls = $matches[1] ?? [];
            $linkTexts = $matches[2] ?? [];

            foreach ($urls as $index => $link) {
                $absoluteUrl = $this->resolveUrl($url, $link);
                $linkText = strip_tags(trim($linkTexts[$index] ?? ''));

                if (!preg_match('/^https?:\/\//', $absoluteUrl)) {
                    continue;
                }

                try {
                    $response = $client->head($absoluteUrl);

                    if ($response->getStatusCode() >= 400) {
                        $brokenLinks[] = [
                            'url' => $absoluteUrl,
                            'status' => 'Broken (' . $response->getStatusCode() . ')',
                            'entryId' => $entry?->id,
                            'entryTitle' => $entry?->title ?? $entry?->slug ?? 'N/A',
                            'entryUrl' => $entry ? $entry->getCpEditUrl() : null,
                            'linkText' => $linkText, 
                            'field' => 'todo',
                            'pageUrl' => $url,
                        ];
                    }
                } catch (\Throwable $e) {
                    $brokenLinks[] = [
                        'url' => $absoluteUrl,
                        'status' => 'Unreachable',
                        'error' => $e->getMessage(),
                        'entryId' => $entry?->id,
                        'entryTitle' => $entry?->title ?? $entry?->slug ?? 'N/A',
                        'entryUrl' => $entry ? $entry->getCpEditUrl() : null,
                        'linkText' => $linkText,
                        'field' => 'todo',
                        'pageUrl' => $url,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Craft::error("Error crawling page URL: $url - " . $e->getMessage(), __METHOD__);
        }
    }

    private function resolveUrl(string $baseUrl, string $relativeUrl): string
    {
        return (string) \GuzzleHttp\Psr7\UriResolver::resolve(
            new \GuzzleHttp\Psr7\Uri($baseUrl),
            new \GuzzleHttp\Psr7\Uri($relativeUrl)
        );
    }
}
