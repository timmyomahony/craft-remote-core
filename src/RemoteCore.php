<?php

namespace weareferal\remotecore;

use craft\base\Plugin;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use yii\base\Event;

use weareferal\remotecore\services\providers\AWSProvider;
use weareferal\remotecore\services\providers\BackblazeProvider;
use weareferal\remotecore\services\providers\DropboxProvider;
use weareferal\remotecore\services\providers\GoogleDriveProvider;
use weareferal\remotecore\services\providers\DigitalOceanProvider;


class RemoteCore extends Plugin
{
    public $hasCpSettings = true;

    public static $plugin;

    public $schemaVersion = '1.0.0';

    public function init()
    {
        parent::init();

        self::$plugin = $this;
        
        $Provider = null;
        switch ($this->getSettings()->cloudProvider) {
            case "s3":
                $Provider = AWSProvider::class;
            case "b2":
                $Provider = BackblazeProvider::class;
            case "google":
                $Provider = GoogleDriveProvider::class;
            case "dropbox":
                $Provider = DropboxProvider::class;
            case "do":
                $Provider = DigitalOceanProvider::class;
        }

        $this->setComponents([
            'provider' => $Provider($this)
        ]);

        // Extra urls
        if ($this->getSettings()->cloudProvider == "google") {
            Event::on(
                UrlManager::class,
                UrlManager::EVENT_REGISTER_CP_URL_RULES,
                function (RegisterUrlRulesEvent $event) {
                    $event->rules['remote-core/google-drive/auth'] = 'remote-core/google-drive/auth';
                    $event->rules['remote-core/google-drive/auth-redirect'] = 'remote-core/google-drive/auth-redirect';
                }
            );
        }
    }
}
