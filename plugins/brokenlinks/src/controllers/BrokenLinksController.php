<?php

namespace craigclement\craftbrokenlinks\controllers;

use craft\web\Controller;
use craigclement\craftbrokenlinks\services\LinkCheckerService;
use Craft;

class LinkCheckerController extends Controller
{
    protected array|int|bool $allowAnonymous = false; // Only accessible to logged-in users

    public function actionRunCrawl()
    {
        // Log a message that the endpoint has been hit
        Craft::debug('Run Crawl endpoint hit', __METHOD__);

        // Ensure this method is responding with JSON
        Craft::$app->response->format = \yii\web\Response::FORMAT_JSON;

        // Call your service to get broken links
        $linkCheckerService = new LinkCheckerService();
        $brokenLinks = $linkCheckerService->checkLinks();

        // Log the response before returning it
        Craft::debug('Broken links detected: ' . print_r($brokenLinks, true), __METHOD__);

        // Return JSON response
        return $this->asJson([
            'success' => true,
            'message' => 'Crawl complete successfully',
            'data' => $brokenLinks,
        ]);
    }
}



