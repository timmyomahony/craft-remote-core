<?php

namespace weareferal\remoteservices\providers;

use Craft;

use weareferal\remoteservices\providers\AWSProvider;


/**
 * Digital Ocean Spaces provider
 * 
 * Because the Spaces API is based on AWS S3, we can use the same PHP SDK
 * library and simply point to a different endpoint:
 * 
 * https://www.digitalocean.com/docs/spaces/resources/s3-sdk-examples/
 */
class DigitalOceanProvider extends AWSProvider
{
    private $name = "Digital Ocean Spaces";

    protected function getEndpoint()
    {
        $settings = $this->getSettings();
        return "https://{$settings['doRegionName']}.digitaloceanspaces.com";
    }

    protected function getSettings()
    {
        return [
            'accessKey' => Craft::parseEnv($this->settings->doAccessKey),
            'secretKey' => Craft::parseEnv($this->settings->doSecretKey),
            // For whatever reason, to use the AWS SDK with Digital Ocena
            // you set the region name in the API options to us-east-1 while
            // adding the actual Digital Ocean region to the endpoint URL
            // 
            // See for more:
            // https://www.digitalocean.com/docs/spaces/resources/s3-sdk-examples/
            'doRegionName' => Craft::parseEnv($this->settings->doRegionName),
            'regionName' => 'us-east-1',
            'bucketName' => Craft::parseEnv($this->settings->doSpacesName),
            'bucketPath' => Craft::parseEnv($this->settings->doSpacesPath)
        ];
    }
}
