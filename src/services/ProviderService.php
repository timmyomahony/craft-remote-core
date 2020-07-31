<?php
namespace weareferal\remotecore\services;

use weareferal\remotecore\RemoteCore;
use weareferal\remotecore\helpers\ZipHelper;
use weareferal\remotecore\helpers\RemoteFile;

use weareferal\remotecore\services\providers\AWSProvider;
use weareferal\remotecore\services\providers\BackblazeProvider;
use weareferal\remotecore\services\providers\DropboxProvider;
use weareferal\remotecore\services\providers\GoogleDriveProvider;
use weareferal\remotecore\services\providers\DigitalOceanProvider;

use Craft;
use craft\base\Component;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;


/**
 * Provider interface
 * 
 * Methods that all new providers must implement
 * 
 * @since 1.0.0
 */
interface ProviderInterface
{
    public function isConfigured(): bool;
    public function isAuthenticated(): bool;
    public function list($filterExtensions): array;
    public function push($path);
    public function pull($key, $localPath);
    public function delete($key);
}


/**
 * Base Prodiver
 * 
 * A remote cloud backend provider for sending and receiving files to and from
 */
abstract class ProviderService extends Component implements ProviderInterface
{

    protected $plugin;
    public $name;

    function __construct($plugin) {
        $this->plugin = $plugin;
    }

    /**
     * Provider is configured
     * 
     * @return boolean whether this provider is properly configured
     * @since 1.1.0
     */
    public function isConfigured(): bool
    {
        return false;
    }

    /**
     * User is authenticated with the provider
     * 
     * @return boolean
     * @since 1.1.0
     */
    public function isAuthenticated(): bool
    {
        // TODO: we should perform an actual authentication test
        return true;
    }

    /**
     * Return the remote database filenames
     * 
     * @return array An array of label/filename objects
     * @since 1.0.0
     */
    public function listDatabases(): array
    {
        return RemoteFile::createArray($this->list(".sql"));
    }

    /**
     * Return the remote volume filenames
     * 
     * @return array An array of label/filename objects
     * @since 1.0.0
     */
    public function listVolumes(): array
    {
        return RemoteFile::createArray($this->list(".zip"));
    }

    /**
     * Push database to remote provider
     * 
     * @return string The filename of the newly created Remote Sync
     * @since 1.0.0
     */
    public function pushDatabase()
    {
        $settings = $this->getSettings();
        $filename = $this->createFilename();
        $path = $this->createDatabaseDump($filename);

        Craft::debug('New database sql path:' . $path, 'remote-core');

        $this->push($path);

        if (! property_exists($settings, 'keepLocal') || ! $settings->keepLocal) {
            Craft::debug('Deleting local volume zip file:' . $path, 'remote-core');
            unlink($path);
        }

        return $filename;
    }

    /**
     * Push all volumes to remote provider
     * 
     * @return string The filename of the newly created Remote Sync
     * @return null If no volumes exist
     * @since 1.0.0
     */
    public function pushVolumes(): string
    {
        $settings = $this->getSettings();
        $filename = $this->createFilename();
        $path = $this->createVolumesZip($filename);

        Craft::debug('New volume zip path:' . $path, 'remote-core');

        $this->push($path);

        if (! property_exists($settings, 'keepLocal') || ! $settings->keepLocal) {
            Craft::debug('Deleting local database sql file:' . $path, 'remote-core');
            unlink($path);
        }

        return $filename;
    }

    /**
     * Pull and restore remote database file
     * 
     * @param string $filename the file to restore
     */
    public function pullDatabase($filename)
    {
        // Before pulling a database, backup the local
        $settings = $this->getSettings();
        if (property_exists($settings, 'keepEmergencyBackup') && $settings->keepEmergencyBackup) {
            $this->createDatabaseDump("emergency-backup");
        }

        $path = $this->getLocalDir() . DIRECTORY_SEPARATOR . $filename;
        $this->pull($filename, $path);
        Craft::$app->getDb()->restore($path);

        # Clear any items in the restoreed database queue table
        # See https://github.com/weareferal/craft-remote-sync/issues/16
        if ($settings->useQueue) {
            Craft::$app->queue->releaseAll();
        }

        unlink($path);
    }

    /**
     * Pull Volume
     * 
     * Pull and restore a particular remote volume .zip file.
     * 
     * @param string The file to restore
     * @since 1.0.0
     */
    public function pullVolume($filename)
    {
        // Before pulling volumes, backup the local
        $settings = $this->getSettings();
        if (property_exists($settings, 'keepEmergencyBackup') && $settings->keepEmergencyBackup) {
            $this->createVolumesZip("emergency-backup");
        }

        $path = $this->getLocalDir() . DIRECTORY_SEPARATOR . $filename;
        $this->pull($filename, $path);
        $this->restoreVolumesZip($path);
        unlink($path);
    }

    /**
     * Delete Database
     * 
     * Delete a remote database .sql file
     * 
     * @param string The filename to delete
     * @since 1.0.0
     */
    public function deleteDatabase($filename)
    {
        $this->delete($filename);
    }

    /**
     * Delete Volume
     * 
     * Delete a remote volume .zip file
     * 
     * @param string The filename to delete
     * @since 1.0.0
     */
    public function deleteVolume($filename)
    {
        $this->delete($filename);
    }

    /**
     * Create volumes zip
     * 
     * Generates a temporary zip file of all volumes
     * 
     * @param string $filename the filename to give the new zip
     * @return string $path the temporary path to the new zip file
     * @since 1.0.0
     */
    private function createVolumesZip($filename): string
    {
        $path = $this->getLocalDir() . DIRECTORY_SEPARATOR . $filename . '.zip';
        if (file_exists($path)) {
            unlink($path);
        }

        $volumes = Craft::$app->getVolumes()->getAllVolumes();
        $tmpDirName = Craft::$app->getPath()->getTempPath() . DIRECTORY_SEPARATOR . strtolower(StringHelper::randomString(10));

        if (count($volumes) <= 0) {
            return null;
        }

        foreach ($volumes as $volume) {
            $tmpPath = $tmpDirName . DIRECTORY_SEPARATOR . $volume->handle;
            FileHelper::copyDirectory($volume->rootPath, $tmpPath);
        }

        ZipHelper::recursiveZip($tmpDirName, $path);
        FileHelper::clearDirectory(Craft::$app->getPath()->getTempPath());
        return $path;
    }

    /**
     * Restore Volumes Zip
     * 
     * Unzips volumes to a temporary path and then moves them to the "web" 
     * folder.
     * 
     * @param string $path the path to the zip file to restore
     * @since 1.0.0
     */
    private function restoreVolumesZip($path)
    {
        $volumes = Craft::$app->getVolumes()->getAllVolumes();
        $tmpDirName = Craft::$app->getPath()->getTempPath() . DIRECTORY_SEPARATOR . strtolower(StringHelper::randomString(10));

        ZipHelper::unzip($path, $tmpDirName);

        $folders = array_diff(scandir($tmpDirName), array('.', '..'));
        foreach ($folders as $folder) {
            foreach ($volumes as $volume) {
                if ($folder == $volume->handle) {
                    $dest = $tmpDirName . DIRECTORY_SEPARATOR . $folder;
                    if (!file_exists($volume->rootPath)) {
                        FileHelper::createDirectory($volume->rootPath);
                    } else {
                        FileHelper::clearDirectory($volume->rootPath);
                    }
                    FileHelper::copyDirectory($dest, $volume->rootPath);
                }
            }
        }

        FileHelper::clearDirectory(Craft::$app->getPath()->getTempPath());
    }

    /**
     * Create Database Dump
     * 
     * Uses the underlying Craft 3 "backup/db" function to create a new database
     * backup in local folder.
     * 
     * @param string The file name to give the new backup
     * @return string The file path to the new database dump
     * @since 1.0.0
     */
    private function createDatabaseDump($filename): string
    {
        $path = $this->getLocalDir() . DIRECTORY_SEPARATOR . $filename . '.sql';
        Craft::$app->getDb()->backupTo($path);
        return $path;
    }

    /**
     * Create Filename
     * 
     * Create a unique filename for a backup file. Based on getBackupFilePath():
     * 
     * https://github.com/craftcms/cms/tree/master/src/db/Connection.php
     * 
     * @return string The unique filename
     * @since 1.0.0
     */
    private function createFilename(): string
    {
        $currentVersion = 'v' . Craft::$app->getVersion();
        $systemName = FileHelper::sanitizeFilename(Craft::$app->getInfo()->name, ['asciiOnly' => true]);
        $systemEnv = Craft::$app->env;
        $filename = ($systemName ? $systemName . '_' : '') . ($systemEnv ? $systemEnv . '_' : '') . gmdate('ymd_His') . '_' . strtolower(StringHelper::randomString(10)) . '_' . $currentVersion;
        return mb_strtolower($filename);
    }

    /**
     * Get Local Directory
     * 
     * Return (or creates) the local directory we use to store temporary files.
     * This is a separate folder to the default Craft backup folder.
     * 
     * @return string The path to the local directory
     * @since 1.0.0
     */
    protected function getLocalDir()
    {
        $dir = Craft::$app->path->getStoragePath() . "/" . $this->plugin->getHandle();
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir;
    }

    /**
     * Filter By Extension
     * 
     * Filter an array of filenames by their extension (.sql or .zip)
     * 
     * @param string The file extension to filter by
     * @return array The filtered filenames
     */
    protected function filterByExtension($filenames, $extension)
    {
        $filtered_filenames = [];
        foreach ($filenames as $filename) {
            if (substr($filename, -strlen($extension)) === $extension) {
                array_push($filtered_filenames, basename($filename));
            }
        }
        return $filtered_filenames;
    }

    /**
     * Get Settings
     * 
     * This gives any implementing classes the ability to adjust settings
     * 
     * @since 1.0.0
     * @return object settings
     */
    protected function getSettings()
    {
        return $this->plugin->getSettings();
    }

    
}

