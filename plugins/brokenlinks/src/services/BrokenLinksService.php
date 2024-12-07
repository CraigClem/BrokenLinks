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

        // Log the base URL being crawled
        Craft::info("Starting crawl for base URL: $baseUrl", __METHOD__);

        try {
            // Fetch all entries from Craft CMS
            $entries = Craft::$app->elements->createElementQuery(\craft\elements\Entry::class)
                ->all();

            // Log the number of entries
            Craft::info("Found " . count($entries) . " entries to crawl.", __METHOD__);

            foreach ($entries as $entry) {
                $url = $entry->getUrl();

                if (!$url || in_array($url, $visitedUrls)) {
                    Craft::info("Skipping entry ID: {$entry->id} - URL: $url", __METHOD__);
                    continue; // Skip if no URL or already visited
                }

                $visitedUrls[] = $url;

                // Crawl the entry's URL
                $this->crawlPage($client, $url, $brokenLinks, $visitedUrls, $entry);
            }

            return $brokenLinks;
        } catch (\Throwable $e) {
            // Log and rethrow errors
            Craft::error("Error during crawl: " . $e->getMessage(), __METHOD__);
            Craft::error($e->getTraceAsString(), __METHOD__);
            throw $e;
        }
    }

    private function crawlPage(Client $client, string $url, array &$brokenLinks, array &$visitedUrls, $entry = null): void
    {
        try {
            $response = $client->get($url);
            $html = $response->getBody()->getContents();
    
            // Extract all anchor hrefs from the page
            preg_match_all('/<a\s+(?:[^>]*?\s+)?href="([^"]*)"/i', $html, $matches);
            $urls = $matches[1] ?? [];
    
            foreach ($urls as $link) {
                $absoluteUrl = $this->resolveUrl($url, $link);
    
                // Skip non-HTTP(S) links
                if (!preg_match('/^https?:\/\//', $absoluteUrl)) {
                    continue;
                }
    
                try {
                    $response = $client->head($absoluteUrl);
    
                    if ($response->getStatusCode() >= 400) {
                        Craft::info("Broken link detected: $absoluteUrl", __METHOD__);
                        $brokenLinks[] = [
                            'url' => $absoluteUrl,
                            'status' => 'Broken (' . $response->getStatusCode() . ')',
                            'entryId' => $entry?->id,
                            'entryTitle' => $entry?->title ?? $entry?->slug ?? 'N/A',
                            'entryUrl' => $entry ? $entry->getCpEditUrl() : null, // Use getCpEditUrl()
                            'field' => 'N/A',
                            'pageUrl' => $url,
                        ];
                    }
                } catch (\Exception $e) {
                    Craft::error("Error with link: $absoluteUrl - " . $e->getMessage(), __METHOD__);
                    $brokenLinks[] = [
                        'url' => $absoluteUrl,
                        'status' => 'Unreachable',
                        'error' => $e->getMessage(),
                        'entryId' => $entry?->id,
                        'entryTitle' => $entry?->title ?? $entry?->slug ?? 'N/A',
                        'entryUrl' => $entry ? $entry->getCpEditUrl() : null, // Use getCpEditUrl()
                        'field' => 'N/A',
                        'pageUrl' => $url,
                    ];
                }
            }
        } catch (\Exception $e) {
            Craft::error("Error crawling page URL: $url - " . $e->getMessage(), __METHOD__);
            $brokenLinks[] = [
                'url' => $url,
                'status' => 'Unreachable',
                'error' => $e->getMessage(),
                'entryId' => $entry?->id,
                'entryTitle' => $entry?->title ?? $entry?->slug ?? 'N/A',
                'entryUrl' => $entry ? $entry->getCpEditUrl() : null, // Use getCpEditUrl()
                'field' => 'N/A',
                'pageUrl' => $url,
            ];
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
