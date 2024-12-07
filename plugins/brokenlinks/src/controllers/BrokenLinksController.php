<?php

namespace craigclement\craftbrokenlinks\controllers;

use craft\web\Controller;
use craigclement\craftbrokenlinks\services\BrokenLinksService;
use Craft;

class BrokenLinksController extends Controller
{
    protected array|int|bool $allowAnonymous = true;

    public function actionIndex(): string
    {
        return $this->renderTemplate('brokenlinks/index');
    }

    public function actionRunCrawl()
    {
        Craft::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $baseUrl = Craft::$app->request->getQueryParam('url', 'https://brokenlinks.ddev.site');
        try {
            $service = new BrokenLinksService();
            $brokenLinks = $service->crawlSite($baseUrl);

            return $this->asJson([
                'success' => true,
                'message' => 'Crawl completed successfully.',
                'data' => $brokenLinks,
            ]);
        } catch (\Throwable $e) {
            Craft::error("Error during crawl: " . $e->getMessage(), __METHOD__);

            return $this->asJson([
                'success' => false,
                'message' => 'An error occurred during the crawl.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
