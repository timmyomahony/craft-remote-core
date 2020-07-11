<?php

namespace weareferal\remotecore;

use Craft;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use yii\base\Event;
use yii\base\Module;

use weareferal\remotecore\services\ProviderFactory;


class RemoteCoreModule extends Module
{
    public function init() {
        $this->setComponents([
            'providerFactory' => ProviderFactory::class
        ]);
    }
}
