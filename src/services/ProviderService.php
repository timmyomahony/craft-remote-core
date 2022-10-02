<?php

namespace weareferal\remotecore\services;

use Throwable;

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

    function __construct($plugin)
    {
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

        try {
            $this->push($path);
        } catch (Throwable $e) {
            Craft::debug("Database push failed, cleaning up local zip file:" . $path, "remote-core");
            $this->rmPath($path);
            throw $e;
        }

        if (!property_exists($settings, 'keepLocal') || !$settings->keepLocal) {
            Craft::debug('Deleting local database zip file:' . $path, 'remote-core');
            $this->rmPath($path);
        }

        return $filename;
    }

    /**
     * Push all volumes to remote provider
     * 
     * @return string The filename of the newly created synced file
     * @return null If no volumes exist
     * @since 1.0.0
     */
    public function pushVolumes(): string
    {
        $filename = $this->createFilename();
        $tmpDirName = $this->createTmpDirName();
        $tmpZipPath = $this->createTmpZipPath($filename);
        $time = microtime(true);
        $settings = $this->getSettings();

        // Copy volume files to tmp folder
        try {
            $this->copyVolumeFilesToTmp($tmpDirName);
        } catch (Throwable $e) {
            Craft::debug("Copying volume files locally failed, cleaning up tmp directory:" . $tmpDirName, "remote-core");
            $this->rmDir($tmpDirName);
            throw $e;
        }

        // Zip up the locally copied volume files
        try {
            $this->createVolumesZip($tmpDirName, $tmpZipPath);
        } catch (Throwable $e) {
            Craft::debug("Zipping local volume files failed, cleaning up tmp directory and zip file.", "remote-core");
            Craft::debug("- " . $tmpDirName, "remote-core");
            Craft::debug("- " . $tmpZipPath, "remote-core");
            $this->rmDir($tmpDirName);
            $this->rmPath($tmpZipPath);
            throw $e;
        }

        $this->rmDir($tmpDirName);

        // Push zip to remote destination
        try {
            $this->push($tmpZipPath);
        } catch (Throwable $e) {
            Craft::debug("Volume push failed, cleaning up local volume zip file:"  . $tmpZipPath, "remote-core");
            $this->rmPath($tmpZipPath);
            throw $e;
        }

        // Keep or delete the local zip file
        if (!property_exists($settings, 'keepLocal') || !$settings->keepLocal) {
            Craft::debug('Deleting tmp local volume zip file:' . $tmpZipPath, 'remote-core');
            $this->rmPath($tmpZipPath);
        }

        Craft::debug("Volumes successfully pushed in : " . (string) (microtime(true) - $time)  . " seconds", "remote-core");

        return $filename;
    }

    /**
     * Pull and restore remote database file
     * 
     * @param string $filename the file to restore
     */
    public function pullDatabase($filename)
    {
        $settings = $this->getSettings();
        $path = $this->getLocalDir() . DIRECTORY_SEPARATOR . $filename;

        // Before pulling a database, backup the local
        if (property_exists($settings, 'keepEmergencyBackup') && $settings->keepEmergencyBackup) {
            $this->createDatabaseDump("emergency-backup");
        }

        // Pull down the remote volume zip file
        try {
            $this->pull($filename, $path);
        } catch (Throwable $e) {
            Craft::debug("Database pull failed, cleaning up local file:" . $path, "remote-core");
            $this->rmPath($path);
            throw $e;
        }

        // Restore the locally pulled database backup
        try {
            Craft::$app->getDb()->restore($path);
        } catch (Throwable $e) {
            Craft::debug("Database restore failed, cleaning up local file:" . $path, "remote-core");
            $this->rmPath($path);
            throw $e;
        }

        // Clear any items in the restoreed database queue table
        // See https://github.com/weareferal/craft-remote-sync/issues/16
        if ($settings->useQueue) {
            Craft::$app->queue->releaseAll();
        }

        $this->rmPath($path);
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
        $settings = $this->getSettings();

        // Before pulling volumes, create an emergency backup
        if (property_exists($settings, 'keepEmergencyBackup') && $settings->keepEmergencyBackup) {
            $emergencyTmpDir = $this->createTmpDirName();
            $emergencyTmpZipPath = $this->createTmpZipPath("emergency-backup");
            try {
                $this->copyVolumeFilesToTmp($emergencyTmpDir);
                $this->createVolumesZip($emergencyTmpDir, $emergencyTmpZipPath);
                $this->rmDir($emergencyTmpDir);
            } catch (Throwable $e) {
                Craft::debug("Emergency volume backup failed, cleaning up files and folders", "remote-core");
                Craft::debug("- " . $emergencyTmpDir, "remote-core");
                Craft::debug("- " . $emergencyTmpZipPath, "remote-core");
                $this->rmPath($emergencyTmpZipPath);
                $this->rmDir($emergencyTmpDir);
                throw $e;
            }
        }

        $tmpZipPath = $this->getLocalDir() . DIRECTORY_SEPARATOR . $filename;

        // Pull down the remote volume zip file
        try {
            $this->pull($filename, $tmpZipPath);
        } catch (Throwable $e) {
            Craft::debug("Volume pull failed, cleaning up local file:" . $tmpZipPath, "remote-core");
            $this->rmPath($tmpZipPath);
            throw $e;
        }

        // Restore the locally pulled volume zip file
        try {
            $this->restoreVolumesZip($tmpZipPath);
        } catch (Throwable $e) {
            Craft::debug("Volume restore failed, cleaning up local file:" . $tmpZipPath, "remote-core");
            $this->rmPath($tmpZipPath);
            throw $e;
        }

        $this->rmPath($tmpZipPath);
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
     * Copy Volume Files To Tmp
     * 
     * Copy all files across all volumes to a local temporary directory, ready
     * to be zipped.
     * 
     * @return bool if the copy was successful
     */
    private function copyVolumeFilesToTmp($tmpDir): bool
    {
        $volumes = Craft::$app->getVolumes()->getAllVolumes();
        $time = microtime(true);

        if (count($volumes) <= 0) {
            Craft::debug("No volumes configured, skipping copy", "remote-core");
            return false;
        }

        foreach ($volumes as $volume) {
            // Get all files in the volume.
            $fileSystem = $volume->getFs();
            $fsListings = $fileSystem->getFileList('/', true);

            // Create tmp location
            $tmpPath = $tmpDir . DIRECTORY_SEPARATOR  . $volume->handle;
            if (!file_exists($tmpPath)) {
                mkdir($tmpPath, 0777, true);
            }

            foreach ($fsListings as $fsListing) {
                $localDirname = $tmpPath . DIRECTORY_SEPARATOR . $fsListing->getDirname();
                $localPath = $tmpPath . DIRECTORY_SEPARATOR . $fsListing->getUri();

                if ($fsListing->getIsDir()) {
                    mkdir($localPath, 0777, $recursive = true);
                } else {
                    if ($localDirname && !file_exists($localDirname)) {
                        mkdir($localDirname, 0777, true);
                    }
                    $src = $fileSystem->getFileStream($fsListing->getUri());
                    $dst = fopen($localPath, 'w');
                    stream_copy_to_stream($src, $dst);
                    fclose($src);
                    fclose($dst);
                }
            }
        }

        Craft::debug("Volume successfully files copied to local tmp folder in " . (string) (microtime(true) - $time)  . " seconds", "remote-core");

        return true;
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
    private function createVolumesZip($srcDir, $dstPath): string
    {
        if (file_exists($dstPath)) {
            $this->rmPath($dstPath);
        }

        ZipHelper::recursiveZip($srcDir, $dstPath);

        return $dstPath;
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
    private function restoreVolumesZip($zipPath)
    {
        $volumes = Craft::$app->getVolumes()->getAllVolumes();
        $tmpDir = $this->createTmpDirName();

        // Unzip files to temp folder
        ZipHelper::unzip($zipPath, $tmpDir);

        // Copy all files to the volume
        $dirs = array_diff(scandir($tmpDir), array('.', '..'));
        foreach ($dirs as $dir) {
            Craft::debug("-- unzipped folder: " . $dir, "remote-core");
            foreach ($volumes as $volume) {
                if ($dir == $volume->handle) {
                    // Send to volume backend
                    $absDir = $tmpDir . DIRECTORY_SEPARATOR . $dir;
                    $files = FileHelper::findFiles($absDir);
                    foreach ($files as $file) {
                        Craft::debug("-- " . $file, "remote-core");
                        $fs = $volume->getFs();
                        if (is_file($file)) {
                            $relPath = str_replace($tmpDir . DIRECTORY_SEPARATOR . $volume->handle, '', $file);
                            $stream = fopen($file, 'r');
                            $fs->writeFileFromStream($relPath, $stream);
                            fclose($stream);
                        }
                    }
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
        $systemName = FileHelper::sanitizeFilename(Craft::$app->getSystemName(), ['asciiOnly' => true]);
        $systemEnv = Craft::$app->env;
        //$filename = ($systemName ? $systemName . '_' : '') . ($systemEnv ? $systemEnv . '_' : '') . gmdate('ymd_His') . '_' . strtolower(StringHelper::randomString(10)) . '_' . $currentVersion;
        $filename = '[' . $systemName . '][' . ($systemEnv ?: 'unknown') . '][' . gmdate('ymd_His') . '][' . strtolower(StringHelper::randomString(10)) . '][' . $currentVersion . ']';
        return mb_strtolower($filename);
    }

    /**
     * Create Tmp Dir Name
     * 
     * Create a random temporary directory path
     * 
     * NOTE: This doesn't actually create the directory
     * 
     * @since 1.1.0
     * @return string a path to a random directory
     */
    private function createTmpDirName(): string
    {
        return Craft::$app->getPath()->getTempPath() . DIRECTORY_SEPARATOR . strtolower(StringHelper::randomString(10));
    }

    /**
     * Generate Temporary Zip Path 
     * 
     * Note: This doesn't actually create the zip file, just the path. 
     */
    private function createTmpZipPath($filename): string
    {
        return $this->getLocalDir() . DIRECTORY_SEPARATOR . $filename . '.zip';
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

    /**
     * Remove Directory
     * 
     */
    private function rmDir($dir)
    {
        FileHelper::clearDirectory($dir);
        if (file_exists($dir)) {
            rmdir($dir);
        }
    }

    /**
     * Remote Path
     * 
     */
    private function rmPath($path)
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }
}
