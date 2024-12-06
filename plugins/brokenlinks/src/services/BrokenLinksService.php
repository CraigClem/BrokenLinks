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

        // Start the crawl with the base URL
        $this->crawlPage($client, $baseUrl, $brokenLinks, $visitedUrls);

        return $brokenLinks;
    }

    private function crawlPage(Client $client, string $url, array &$brokenLinks, array &$visitedUrls): void
    {
        if (in_array($url, $visitedUrls)) {
            return; // Skip already visited URLs
        }

        $visitedUrls[] = $url;

        try {
            $response = $client->get($url);

            // Parse the HTML to find all anchor tags and their href attributes
            $html = $response->getBody()->getContents();
            preg_match_all('/<a\s+(?:[^>]*?\s+)?href="([^"]*)"/i', $html, $matches);
            $urls = $matches[1] ?? [];

            foreach ($urls as $link) {
                // Resolve relative links
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
                        ];
                    }
                } catch (\Exception $e) {
                    $brokenLinks[] = [
                        'url' => $absoluteUrl,
                        'status' => 'Unreachable',
                        'error' => $e->getMessage(),
                    ];
                }
            }
        } catch (\Exception $e) {
            // Handle page fetch errors
            $brokenLinks[] = [
                'url' => $url,
                'status' => 'Unreachable',
                'error' => $e->getMessage(),
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
