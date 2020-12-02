<?php

namespace weareferal\remotecore\assets\RemoteCoreUtility;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;


class RemoteCoreUtilityAsset extends AssetBundle
{
    public function init()
    {
        $this->sourcePath = __DIR__ . '/dist';

        $this->depends = [
            CpAsset::class,
        ];

        // $this->js = [
        //     'js/RemoteCoreUtility.js'
        // ];

        $this->css = [
            'css/RemoteCoreUtility.css',
        ];

        parent::init();
    }
}
