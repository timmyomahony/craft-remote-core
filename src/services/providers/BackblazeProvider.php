<?php

namespace weareferal\remotecore\services\providers;

use Craft;

use weareferal\remotecore\services\providers\AWSProvider;


/**
 * Backblaze B2 provider
 * 
 * The Backblaze B2 backend is compatible with AWS's API so we can use the
 * official AWS PHP SDK as outlined:
 * 
 * https://help.backblaze.com/hc/en-us/articles/360047425453
 */
class BackblazeProvider extends AWSProvider
{
    public $name = "Backblaze B2";

    protected function getEndpoint(): ?string
    {
        $regionName = Craft::parseEnv($this->plugin->settings->b2RegionName);
        return "https://s3.{$regionName}.backblazeb2.com";
    }

    protected function getAccessKey(): ?string {
        return Craft::parseEnv($this->plugin->settings->b2KeyID); 
    }

    protected function getSecretKey(): ?string {
        return Craft::parseEnv($this->plugin->settings->b2AppKey); 
    }

    protected function getRegionName(): ?string {
        return Craft::parseEnv($this->plugin->settings->b2RegionName); 
    }

    protected function getBucketName(): ?string {
        return Craft::parseEnv($this->plugin->settings->b2BucketName); 
    }

    protected function getBucketPath(): ?string {
        return Craft::parseEnv($this->plugin->settings->b2BucketPath); 
    }
}
