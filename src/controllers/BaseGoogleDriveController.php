<?php

namespace weareferal\remotecore\controllers;

use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

use Craft;
use craft\web\Controller;
use craft\web\View;

use weareferal\remotecore\exceptions\ProviderException;


abstract class BaseGoogleDriveController extends Controller
{

    protected function pluginInstance() {
        return null;
    }

    /**
     * Require the plugin to have been set as enabled in the settings
     */
    public function requirePluginEnabled()
    {
        if (!$this->pluginInstance()->getSettings()->enabled) {
            throw new BadRequestHttpException('Plugin is not enabled');
        }
    }

    /**
     * Require that Google Drive has been selected as the current provider
     *
     */
    public function requireGoogleDriveProvider()
    {
        if (!$this->pluginInstance()->getSettings()->cloudProvider == 'google') {
            throw new BadRequestHttpException('Google Drive provider not selected');
        }
    }

    /**
     * Auth with Google
     * 
     * https://developers.google.com/docs/api/quickstart/php
     */
    public function actionAuth()
    {
        $this->requireCpRequest();
        $this->requirePluginEnabled();
        $this->requireGoogleDriveProvider();

        $plugin = $this->pluginInstance();
        $provider = $this->pluginInstance()->provider;
        $client = $provider->getClient();
        $isExpired = $client->isAccessTokenExpired();

        // Try refresh token 
        if ($isExpired) {
            $refreshToken = $client->getRefreshToken();
            if ($refreshToken != null) {
                $client->fetchAccessTokenWithRefreshToken($refreshToken);
                $isExpired = false;
            }
        }

        if (!$isExpired) {
            Craft::$app->session->setFlash('notice', Craft::t("remote-core", "Google Drive already authenticated"));
            return $this->redirect("/admin/settings/plugins/remote-core");
        }

        $externalOAuthUrl = $client->createAuthUrl();

        return $this->renderTemplate('remote-core/google-drive/auth.twig', [
            'plugin' => $plugin,
            'url' => $externalOAuthUrl
        ], View::TEMPLATE_MODE_CP);
    }

    /**
     * Handle Google auth response
     * 
     * Save the access token that is returned via the code parameter and save
     * it to the settings
     */
    public function actionAuthRedirect()
    {
        $this->requireCpRequest();
        $this->requirePluginEnabled();
        $this->requireGoogleDriveProvider();

        $plugin = $this->pluginInstance();

        $code = Craft::$app->getRequest()->get('code');
        if (!$code) {
            throw new NotFoundHttpException();
        }

        $client = $plugin->provider->getClient();
        $accessToken = $client->fetchAccessTokenWithAuthCode($code);
        $client->setAccessToken($accessToken);

        // Check to see if there was an error.
        if (array_key_exists('error', $accessToken)) {
            throw new ProviderException(join(', ', $accessToken));
        }

        // Save the access token to the storage folder
        $tokenPath = $plugin->provider->getTokenPath();
        if (!file_exists(dirname($tokenPath))) {
            mkdir(dirname($tokenPath), 0700, true);
        }
        file_put_contents($tokenPath, json_encode($client->getAccessToken()));

        return $this->renderTemplate('remote-core/google-drive/auth-redirect.twig', [
            'plugin' => $plugin
        ], View::TEMPLATE_MODE_CP);
    }
}
