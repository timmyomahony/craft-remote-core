<?php
namespace weareferal\remotecore\services;

use weareferal\remotecore\services\providers\AWSProvider;
use weareferal\remotecore\services\providers\BackblazeProvider;
use weareferal\remotecore\services\providers\DropboxProvider;
use weareferal\remotecore\services\providers\GoogleDriveProvider;
use weareferal\remotecore\services\providers\DigitalOceanProvider;
use weareferal\remotecore\services\providers\OtherS3Provider;

use Craft;
use craft\base\Component;


/**
 * Provider Factory
 * 
 * A factory service that returns a provider intance based on the calling
 * plugins "cloudProvider" setting.
 */
class ProviderFactory extends Component {
    public function create($plugin) {
        $ProviderClass = null;
        switch ($plugin->getSettings()->cloudProvider) {
            case "s3":
                $ProviderClass = AWSProvider::class;
                break;
            case "b2":
                $ProviderClass = BackblazeProvider::class;
                break;
            case "google":
                $ProviderClass = GoogleDriveProvider::class;
                break;
            case "dropbox":
                $ProviderClass = DropboxProvider::class;
                break;
            case "do":
                $ProviderClass = DigitalOceanProvider::class;
                break;
            case "other-s3":
                $ProviderClass = OtherS3Provider::class;
                break;
        }
        return new $ProviderClass($plugin);
    }
}
