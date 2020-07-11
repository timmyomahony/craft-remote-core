<?php

namespace weareferal\remotecore;

use craft\events\RegisterTemplateRootsEvent;
use craft\web\View;
use yii\base\Event;
use yii\base\Module;

use weareferal\remotecore\services\ProviderFactory;

class RemoteCoreModule extends Module
{
    public function init() {
        // Register provider factory
        $this->setComponents([
            'providerFactory' => ProviderFactory::class
        ]);

        // Register shared templates
        Event::on(
            View::class,
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $e) {
                $e->roots['remote-core'] = $this->getBasePath().DIRECTORY_SEPARATOR.'templates';
            });
    }
}
