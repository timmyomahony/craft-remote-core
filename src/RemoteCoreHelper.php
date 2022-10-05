<?php

namespace weareferal\remotecore;

use Craft;
use weareferal\remotecore\RemoteCoreModule;

/**
 * Remote Core Helper
 * 
 * A helper class to register the remote core module when a plugin
 * is initialised. 
 * 
 * Note: there is only one instance of this module. All it does
 * is expose a service that lets plugins create providers
 */
abstract class RemoteCoreHelper
{
    public static function registerModule()
    {
        $moduleId = 'remote-core';
        if (!Craft::$app->hasModule($moduleId)) {
            Craft::$app->setModule($moduleId, new RemoteCoreModule($moduleId));
            Craft::$app->getModule($moduleId);
        }
    }
   
}
