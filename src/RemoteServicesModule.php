<?php

namespace weareferal\remoteservices;

use Craft;

class RemoteServicesModule extends yii\base\Module
{
    public function init()
    {
        Craft::setAlias('@bar', __DIR__);
        parent::init();
    }
}
