<?php
namespace weareferal\remotecore\services;

use weareferal\remotecore\services\providers\AWSProvider;
use weareferal\remotecore\services\providers\BackblazeProvider;
use weareferal\remotecore\services\providers\DropboxProvider;
use weareferal\remotecore\services\providers\GoogleDriveProvider;
use weareferal\remotecore\services\providers\DigitalOceanProvider;

use Craft;
use craft\base\Component;


/**
 * 
 */
class ProviderFactory extends Component {
    public function create($plugin) {
        Craft::debug('Creating provider for: ' . $plugin->name, 'remote-core');
        Craft::debug('Cloud provider: ' . $plugin->getSettings()->cloudProvider, 'remote-core');
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
                Craft::debug('Test Dropbox', 'remote-core');
                $ProviderClass = DropboxProvider::class;
                break;
            case "do":
                Craft::debug('Test Digital Ocean', 'remote-core');
                $ProviderClass = DigitalOceanProvider::class;
                break;
        }
        return new $ProviderClass($plugin);
    }
}
