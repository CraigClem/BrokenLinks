<?php

namespace craigclement\craftbrokenlinks\controllers;

use craft\web\Controller;
use craigclement\craftbrokenlinks\services\BrokenLinksService;
use Craft;

class BrokenLinksController extends Controller
{
    // Allow anonymous requests only for testing; set to false for production
    protected array|int|bool $allowAnonymous = true;

    /**
     * Render the index page in the Craft CP.
     */
    public function actionIndex(): string
    {
        // Render the index.twig template
        return $this->renderTemplate('brokenlinks/index');
    }

    /**
     * Handle the crawl action and return results as JSON.
     */
    public function actionRunCrawl()
    {
        Craft::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $baseUrl = 'https://brokenlinks.ddev.site'; // Adjust for local development
        $service = new BrokenLinksService();
        $brokenLinks = $service->crawlSite($baseUrl);

        return $this->asJson([
            'success' => true,
            'message' => 'Crawl completed successfully.',
            'data' => $brokenLinks,
        ]);
    }
}
