<?php

namespace weareferal\remotecore\assets\RemoteCoreSettings;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;


class RemoteCoreSettingsAsset extends AssetBundle
{
    public function init()
    {
        $this->sourcePath = "@weareferal/remote-core/assets/RemoteCoreSettings/dist";

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/RemoteCoreSettings.js'
        ];

        parent::init();
    }
}
