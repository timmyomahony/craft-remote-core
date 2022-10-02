<?php

namespace weareferal\remotecore\helpers;

use Craft;
use DateTime;
use DateTimezone;
use weareferal\remotecore\helpers\TimeHelper;

/**
 * Remote file
 * 
 * An internal class data-type that helps us extract the relevent remote
 * file information from the file name.
 */
class RemoteFile
{
    public $filename;
    public $datetime;
    public $env;
    public $version;

    // Regex to capture/match:
    // - Site name
    // - Environment (optional and captured)
    // - Date (required and captured)
    // - Random string
    // - Version (captured)
    // - Extension
    private static $legacyRegex = '/^(?:[a-zA-Z0-9\-]+)\_(?:([a-zA-Z0-9\-]+)\_)?(\d{6}\_\d{6})\_(?:[a-zA-Z0-9]+)\_(?:([va-zA-Z0-9\.\-]+))\.(?:\w{2,10})$/';
    private static $regex = '/^(?:[a-zA-Z0-9\-]+)\_\_(?:([a-zA-Z0-9\-]+)\_\_)?(\d{6}\_\d{6})\_\_(?:[a-zA-Z0-9]+)\_\_(?:([va-zA-Z0-9\.\-]+))\.(?:\w{2,10})$/';

    public function __construct($filename)
    {
        // Extract values from filename
        preg_match(RemoteFile::$regex, $filename, $matches);
        if (count($matches) <= 0) {
            preg_match(RemoteFile::$legacyRegex, $filename, $matches);
        }

        // UTC to timezone
        $datetime = DateTime::createFromFormat(
            'ymd_Gis',
            $matches[2],
            new DateTimeZone("UTC"));
        $datetime->setTimezone(new DateTimeZone(date_default_timezone_get()));
        
        $this->filename = $filename;
        $this->datetime = $datetime;
        $this->env = $matches[1];
        $this->version = $matches[3];
    }

    public static function createArray($filenames) {
        $files = [];

        foreach ($filenames as $filename) {
            try {
                array_push($files, new RemoteFile($filename));
            } catch (\Throwable $e) {
                Craft::$app->getErrorHandler()->logException($e);
            }
        }

        uasort($files, function ($b1, $b2) {
            return $b1->datetime <=> $b2->datetime;
        });

        return array_reverse($files);
    }

    public static function toObject($array, $dateFormat="Y-m-d") {
        $files = [];
        foreach ($array as $i => $file) {
            $timesince = TimeHelper::time_since($file->datetime->getTimestamp());
            $files[$i] = [
                "filename" => $file->filename,
                "date" => $file->datetime->format($dateFormat),
                "time" => $file->datetime->format("H:i:s"),
                "timesince" => $timesince,
                "env" => $file->env,
                "version" => $file->version,
            ];
        }
        return $files;
    }
}
