<?php

namespace weareferal\remoteservices\providers;

use Craft;

use Kunnu\Dropbox\Dropbox;
use Kunnu\Dropbox\DropboxApp;
use Kunnu\Dropbox\DropboxFile;

use weareferal\remoteservices\providers\RemoteProvider;
use weareferal\remoteservices\exceptions\RemoteProviderException;


/**
 * Dropbox Provider
 * 
 * This uses a community/unofficial SDK but it has great documentation
 * 
 * https://github.com/kunalvarma05/dropbox-php-sdk
 * 
 * @todo add regex to the folder path setting so users can't enter incorrect
 * values
 */
class DropboxProvider extends RemoteProvider
{
    /**
     * Is Configured
     * 
     * @return boolean whether this provider is properly configured
     * @since 1.3.0
     */
    public function isConfigured(): bool
    {
        $settings = $this->getSettings();
        return isset($settings->dropboxAppKey) &&
            isset($settings->dropboxSecretKey) &&
            isset($settings->dropboxAccessToken);
    }

    /**
     * Return Dropbox files
     * 
     * https://github.com/kunalvarma05/dropbox-php-sdk/wiki/Working-with-files#list-folder-contents
     * 
     * @param string $extension The file extension to filter the results by
     * @return array[string] An array of filenames returned from Dropbox
     * @since 1.3.0
     * @todo filter results via the API as opposed to our own custom filtering
     */
    public function list($filterExtension): array
    {
        $settings = $this->getSettings();
        $dropboxFolder = Craft::parseEnv($settings->dropboxFolder);

        $dstPath = "/";
        if ($dropboxFolder) {
            $dstPath = $dropboxFolder;
        }

        $dropbox = $this->getClient();
        $folder = $dropbox->listFolder($dstPath);
        $items = $folder->getItems();

        $filenames = [];
        foreach ($items->all() as $item) {
            array_push($filenames, $item->getName());
        }

        if ($filterExtension) {
            return $this->filterByExtension($filenames, $filterExtension);
        }

        return $filenames;
    }

    /**
     * Push a file to Dropbox folder
     * 
     * https://github.com/kunalvarma05/dropbox-php-sdk/wiki/Upload-and-Download-Files#auto-file-upload
     *  
     * @param string $path The full filesystem path to file
     * @since 1.3.0
     */
    public function push($srcPath)
    {
        $srcPathInfo = pathinfo($srcPath);
        $dstPath = $this->getDestinationPath($srcPathInfo['basename']);
        $dropbox = $this->getClient();
        $dropboxFile = new DropboxFile($srcPath);
        $dropbox->upload($dropboxFile, $dstPath, []);
    }

    /**
     * Pull a file from Dropbox folder
     * 
     * https://github.com/kunalvarma05/dropbox-php-sdk/wiki/Upload-and-Download-Files#download-file
     *  
     * @since 1.3.0
     */
    public function pull($filename, $localPath)
    {
        $dstPath = $this->getDestinationPath($filename);
        $dropbox = $this->getClient();
        $file = $dropbox->download($dstPath);
        $contents = $file->getContents();
        file_put_contents($localPath, $contents);
    }

    /**
     * Delete a remote Dropbox file
     * 
     * https://github.com/kunalvarma05/dropbox-php-sdk/wiki/Working-with-files#delete-filefolder
     * 
     * @since 1.3.0
     */
    public function delete($filename)
    {
        $dstPath = $this->getDestinationPath($filename);
        $dropbox = $this->getClient();
        $dropbox->delete($dstPath);
    }

    /**
     * Return the destination file path, including any prefix folder. The
     * path must be of the format "/file.txt" or /folder/file.txt" (with a 
     * opening slash)
     * 
     * @param string $filename The filename for the filename
     * @return string The prefixed filename
     * @since 1.3.0
     */
    private function getDestinationPath($filename): string
    {
        $settings = $this->getSettings();
        $dropboxFolder = Craft::parseEnv($settings->dropboxFolder);
        if ($dropboxFolder) {
            return $dropboxFolder . DIRECTORY_SEPARATOR . $filename;
        }
        return '/' . $filename;
    }

    /**
     * Return a useable Dropbox client object
     * 
     * @return Dropbox The Dropbox service object
     * @since 1.3.0
     */
    private function getClient()
    {
        $settings = $this->getSettings();
        $dropboxAppKey = Craft::parseEnv($settings->dropboxAppKey);
        $dropboxSecretKey = Craft::parseEnv($settings->dropboxSecretKey);
        $dropboxAccessToken = Craft::parseEnv($settings->dropboxAccessToken);
        $app = new DropboxApp($dropboxAppKey, $dropboxSecretKey, $dropboxAccessToken);
        return new Dropbox($app);
    }
}
