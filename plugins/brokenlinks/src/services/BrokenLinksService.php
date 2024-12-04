<?php

namespace craigclement\craftbrokenlinks\services;

use Craft;
use craft\base\Component;
use GuzzleHttp\Client;

class LinkCheckerService extends Component
{
    public function checkLinks(): array
    {
        $brokenLinks = [];
        $client = new Client();

        // Fetch all entries
        $entries = Craft::$app->elements->getCriteria('craft\elements\Entry')->all();

        foreach ($entries as $entry) {
            preg_match_all('/href="([^"]+)"/', $entry->getContent(), $matches);
            $urls = $matches[1] ?? [];

            foreach ($urls as $url) {
                try {
                    $response = $client->head($url);
                    if ($response->getStatusCode() >= 400) {
                        $brokenLinks[] = [
                            'entryId' => $entry->id,
                            'entryTitle' => $entry->title,
                            'url' => $url,
                        ];
                    }
                } catch (\Exception $e) {
                    $brokenLinks[] = [
                        'entryId' => $entry->id,
                        'entryTitle' => $entry->title,
                        'url' => $url,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }

        return $brokenLinks;
    }
}
