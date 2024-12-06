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

        // Fetch all entries from Craft CMS
        $entries = Craft::$app->elements->createElementQuery(\craft\elements\Entry::class)->all();

        foreach ($entries as $entry) {
            $url = $entry->getUrl();
            if (!$url || in_array($url, $visitedUrls)) {
                continue; // Skip if no URL or already visited
            }

            $visitedUrls[] = $url;

            // Crawl the entry's URL
            $this->crawlPage($client, $url, $brokenLinks, $visitedUrls, $entry);
        }

        return $brokenLinks;
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
                        $brokenLinks[] = [
                            'url' => $absoluteUrl,
                            'status' => 'Broken (' . $response->getStatusCode() . ')',
                            'entryId' => $entry?->id,
                            'entryTitle' => $entry?->title,
                            'field' => 'N/A', // Replace if crawling specific fields
                            'pageUrl' => $url,
                        ];
                    }
                } catch (\Exception $e) {
                    $brokenLinks[] = [
                        'url' => $absoluteUrl,
                        'status' => 'Unreachable',
                        'error' => $e->getMessage(),
                        'entryId' => $entry?->id,
                        'entryTitle' => $entry?->title,
                        'field' => 'N/A',
                        'pageUrl' => $url,
                    ];
                }
            }
        } catch (\Exception $e) {
            // Handle unreachable pages
            $brokenLinks[] = [
                'url' => $url,
                'status' => 'Unreachable',
                'error' => $e->getMessage(),
                'entryId' => $entry?->id,
                'entryTitle' => $entry?->title,
                'field' => 'N/A',
                'pageUrl' => $url,
            ];
        }
    }

    private function resolveUrl(string $baseUrl, string $relativeUrl): string
    {
        // Build absolute URL from base and relative
        return (string) \GuzzleHttp\Psr7\UriResolver::resolve(
            new \GuzzleHttp\Psr7\Uri($baseUrl),
            new \GuzzleHttp\Psr7\Uri($relativeUrl)
        );
    }
}
