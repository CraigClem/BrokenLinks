<?php

namespace craigclement\craftbrokenlinks\services;

use Craft;
use craft\elements\Entry;
use GuzzleHttp\Client;
use yii\base\Component;

class LinkCheckerService extends Component
{
    public function checkLinks(): array
    {
        $client = new Client(['timeout' => 5]); // Set a timeout for requests
        $brokenLinks = [];

        // Fetch all entries
        $entries = Craft::$app->elements->createElementQuery(Entry::class)->all();

        foreach ($entries as $entry) {
            $fieldLayout = $entry->getFieldLayout();

            if ($fieldLayout) {
                $fields = $fieldLayout->getFields();

                foreach ($fields as $field) {
                    $fieldHandle = $field->handle;
                    $fieldContent = $entry->$fieldHandle ?? null;

                    // Extract URLs based on field content type
                    $urls = $this->extractUrls($fieldContent);

                    foreach ($urls as $url) {
                        $this->validateUrl($client, $url, $entry, $fieldHandle, $brokenLinks);
                    }
                }
            }
        }
        
        return $brokenLinks;
    }

    /**
     * Extracts URLs from field content based on type.
     *
     * @param mixed $fieldContent
     * @return array
     */
    private function extractUrls($fieldContent): array
    {
        $urls = [];

        if (is_string($fieldContent)) {
            // Extract URLs from HTML content
            preg_match_all('/https?:\/\/[^\s"<>]+/', $fieldContent, $matches);
            $urls = $matches[0] ?? [];
        } elseif (is_object($fieldContent) && method_exists($fieldContent, '__toString')) {
            // Handle object fields that can be cast to string
            $urls = [$fieldContent->__toString()];
        } elseif (is_array($fieldContent)) {
            // Handle array fields (e.g., matrix blocks, custom fields)
            foreach ($fieldContent as $item) {
                if (is_string($item)) {
                    $urls = array_merge($urls, $this->extractUrls($item));
                }
            }
        }

        return $urls;
    }

    /**
     * Validates a URL and appends results to the broken links array.
     *
     * @param Client $client
     * @param string $url
     * @param Entry $entry
     * @param string $fieldHandle
     * @param array &$brokenLinks
     */
    private function validateUrl(Client $client, string $url, Entry $entry, string $fieldHandle, array &$brokenLinks): void
    {
        try {
            $response = $client->head($url);
            $statusCode = $response->getStatusCode();

            if ($statusCode >= 400) {
                // Add broken link to the report
                $brokenLinks[] = [
                    'entryId' => $entry->id,
                    'entryTitle' => $entry->title,
                    'field' => $fieldHandle,
                    'url' => $url,
                    'status' => 'Broken (' . $statusCode . ')',
                ];
            }
        } catch (\Exception $e) {
            // Handle unreachable URLs
            $brokenLinks[] = [
                'entryId' => $entry->id,
                'entryTitle' => $entry->title,
                'field' => $fieldHandle,
                'url' => $url,
                'status' => 'Unreachable',
                'error' => $e->getMessage(),
            ];
        }
    }
}
