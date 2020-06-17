<?php
/**
 * Craft Remote Services module for Craft CMS 3.x
 *
 * Backend services for Remote Sync and Remote Backup
 *
 * @link      https://weareferal.com
 * @copyright Copyright (c) 2020 Timmy O'Mahony
 */

namespace weareferal\remoteservices\services;

use weareferal\remoteservices\providers\AWSProvider;
use weareferal\remoteservices\providers\BackblazeProvider;
use weareferal\remoteservices\providers\DropboxProvider;
use weareferal\remoteservices\providers\GoogleDriveProvider;
use weareferal\remoteservices\providers\DigitalOceanProvider;

use craft\base\Component;


/**
 * Craft service to create remote providers
 * 
 */
class ProviderServices extends Component {
    public static function createProvider($settings) {
        $ProviderClass = null;
        switch ($settings->cloudProvider) {
            case "s3":
                $ProviderClass = AWSProvider::class;
            case "b2":
                $ProviderClass = BackblazeProvider::class;
            case "google":
                $ProviderClass = GoogleDriveProvider::class;
            case "dropbox":
                $ProviderClass = DropboxProvider::class;
            case "do":
                $ProviderClass = DigitalOceanProvider::class;
        }
        return $ProviderClass($settings);
    }
}
