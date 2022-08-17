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
        Craft::debug("Pushing volumes (zip and push)", "remote-core");
        
        $time1 = microtime(true); 

        // Copy volume files to tmp folder and zip it up
        $zipFilename = $this->createFilename();
        $tmpDir = $this->copyVolumeFilesToTmp();
        $zipPath = $this->createVolumesZip($zipFilename, $tmpDir);
        $this->rmDir($tmpDir);
        Craft::debug("- time to create volume zip:" . (string) (microtime(true) - $time1)  . " seconds", "remote-core");
        Craft::debug('- new volume zip path:' . $zipPath, 'remote-core');

        // Push zip to remote destination
        $time2 = microtime(true); 
        $this->push($zipPath);
        Craft::debug("- time to push volume zip: " . (string) (microtime(true) - $time2)  . " seconds", "remote-core");

        // Keep or delete the created zip file
        $settings = $this->getSettings();
        if (! property_exists($settings, 'keepLocal') || ! $settings->keepLocal) {
            Craft::debug('- deleting tmp local volume file:' . $zipPath, 'remote-core');
            if (file_exists($zipPath)) {
                unlink($zipPath);
            } else {
                Craft::debug('- file does not exist: '  . $zipPath, 'remote-core');
            }
        }
        Craft::debug("- total time to create and push volume zip: " . (string) (microtime(true) - $time1)  . " seconds", "remote-core");

        return $zipFilename;
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
        // Before pulling volumes, create an emergency backup
        $settings = $this->getSettings();
        if (property_exists($settings, 'keepEmergencyBackup') && $settings->keepEmergencyBackup) {
            $tmpDir = $this->copyVolumeFilesToTmp();
            $zipPath = $this->createVolumesZip("emergency-backup", $tmpDir);
            $this->rmDir($tmpDir);
        }

        $zipPath = $this->getLocalDir() . DIRECTORY_SEPARATOR . $filename;
        $this->pull($filename, $zipPath);
        $this->restoreVolumesZip($zipPath);
        unlink($zipPath);
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
     * to be zipped up.
     * 
     * @return string $path to the temporary directory containing the volumes
     */
    private function copyVolumeFilesToTmp(): string
    {
        Craft::debug("Copying volume files to temp directory", "remote-core");
        
        $volumes = Craft::$app->getVolumes()->getAllVolumes();
        $tmpDirName = Craft::$app->getPath()->getTempPath() . DIRECTORY_SEPARATOR . strtolower(StringHelper::randomString(10));
        Craft::debug("-- tmp path: "  . $tmpDirName, "remote-core");

        if (count($volumes) <= 0) {
            Craft::debug("-- no volumes configured, skipping zipping", "remote-core");
            return null;
        }
        
        $time = microtime(true); 
        foreach ($volumes as $volume) {
            // Get all files in the volume.
            $fileSystem = $volume->getFs();
            $fsListings = $fileSystem->getFileList('/', true);

            // Create tmp location
            $tmpPath = $tmpDirName . DIRECTORY_SEPARATOR  . $volume->handle;
            if (! file_exists($tmpPath)) {
                mkdir($tmpPath, 0777, true);
            }

            foreach ($fsListings as $fsListing) {
                $localDirname = $tmpPath . DIRECTORY_SEPARATOR . $fsListing->getDirname();
                $localPath = $tmpPath . DIRECTORY_SEPARATOR . $fsListing->getUri();
            
                if ($fsListing->getIsDir()) {
                    mkdir($localPath, 0777, $recursive = true);
                } else {
                    if ($localDirname && ! file_exists($localDirname)) {
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
        Craft::debug("- time to copy volume files to local temp folder:" . (string) (microtime(true) - $time)  . " seconds", "remote-core");

        return $tmpDirName;
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
    private function createVolumesZip($filename, $tmpDir): string
    {
        $path = $this->getLocalDir() . DIRECTORY_SEPARATOR . $filename . '.zip';
        
        Craft::debug("Creating zip from tmp volume files", "remote-core");
        Craft::debug("-- tmp dir: "  . $tmpDir, "remote-core");
        Craft::debug("-- zip path: "  . $path, "remote-core");
        
        if (file_exists($path)) {
            Craft::debug("-- old zip file exists, deleting...", "remote-core");
            unlink($path);
        }

        Craft::debug("-- recursively zipping tmp directory", "remote-core");
        ZipHelper::recursiveZip($tmpDir, $path);

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
    private function restoreVolumesZip($zipPath)
    {
        $volumes = Craft::$app->getVolumes()->getAllVolumes();
        $tmpDir = Craft::$app->getPath()->getTempPath() . DIRECTORY_SEPARATOR . strtolower(StringHelper::randomString(10));

        Craft::debug("Restoring volume files", "remote-core");
        Craft::debug("-- tmp dir: "  . $tmpDir, "remote-core");
        Craft::debug("-- zip path: " . $zipPath, "remote-core");

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
                        if (is_file($file)) {
                            $relPath = str_replace($tmpDir . DIRECTORY_SEPARATOR . $volume->handle, '', $file);
                            $stream = fopen($file, 'r');
                            if ($volume->fileExists($relPath)) {
                                $volume->updateFileByStream($relPath, $stream, []);
                            } else {
                                $volume->createFileByStream($relPath, $stream, []);
                            }
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

    private function rmDir($dir) {
        FileHelper::clearDirectory($dir);
        if (file_exists($dir)) {
            rmdir($dir);
        }
    }
}

