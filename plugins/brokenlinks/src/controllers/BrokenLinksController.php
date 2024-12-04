<?php

namespace craigclement\craftbrokenlinks\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;

class LinkCheckerController extends Controller
{
    // Allows anonymous access to the action
    protected int|bool|array $allowAnonymous = false;

    public function actionRunCrawl(): Response
    {
        $this->requireAcceptsJson();

        // Call your service to check links
        $report = Craft::$app->get('linkCheckerService')->checkLinks();

        // Return the report as a JSON response
        return $this->asJson([
            'success' => true,
            'report' => $report,
        ]);
    }
}
