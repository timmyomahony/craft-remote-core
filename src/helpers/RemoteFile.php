<?php

namespace weareferal\remotecore\helpers;

use weareferal\remotecore\RemoteCoreModule;
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

    // Regex to capture/match:
    // - Site name
    // - Environment (optional and captured)
    // - Date (required and captured)
    // - Random string
    // - Version
    // - Extension
    private static $regex = '/^(?:[a-zA-Z0-9\-]+)\_(?:([a-zA-Z0-9\-]+)\_)?(\d{6}\_\d{6})\_(?:[a-zA-Z0-9]+)\_(?:[va-zA-Z0-9\.\-]+)\.(?:\w{2,10})$/';

    public function __construct($filename)
    {
        // Extract values from filename
        preg_match(RemoteFile::$regex, $filename, $matches);
        $this->filename = $filename;
        $this->datetime = date_create_from_format('ymd_Gis', $matches[2]);
        $this->env = $matches[1];
    }

    public static function createArray($filenames) {
        $files = [];

        foreach ($filenames as $filename) {
            array_push($files, new RemoteFile($filename));
        }

        uasort($files, function ($b1, $b2) {
            return $b1->datetime <=> $b2->datetime;
        });

        return array_reverse($files);
    }

    public static function toHTMLOptions($array, $format="Y-m-d H:i:s") {
        $options = [];
        foreach ($array as $i => $file) {
            $timesince = TimeHelper::time_since($file->datetime->getTimestamp());
            $title = $file->filename . " (" . $timesince . " ago)"; 
            $text = $file->datetime->format($format);
            $options[$i] = [
                "text" => $text,
                "title" => $title
            ];
        }
        return $options;
    }
}
