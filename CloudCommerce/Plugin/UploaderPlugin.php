<?php
/**
 * CloudCommerce S3 AWS Integration - Uploader Plugin
 * 
 * This file contains the plugin for Magento's File Uploader classes
 * to handle uploading files to S3 during the upload process.
 */
namespace CloudCommerce\S3Aws\Plugin;

use Magento\MediaStorage\Model\File\Uploader;
use CloudCommerce\S3Aws\Model\S3Client;
use CloudCommerce\S3Aws\Logger\Logger;
use Magento\Framework\Filesystem\Io\File as IoFile;
use Magento\Framework\Filesystem\DirectoryList;

/**
 * Plugin for Magento\MediaStorage\Model\File\Uploader and Magento\Framework\File\Uploader
 * 
 * This plugin intercepts the save() method to upload files to S3 after they are
 * saved to the local tmp directory. It handles the initial file upload stage
 * before files are moved to their final locations.
 * 
 * Purpose: Ensures uploaded files are synced to S3 during the upload process
 */
class UploaderPlugin
{
    /**
     * @var S3Client
     */
    private $s3Client;

    /**
     * @var IoFile
     */
    private $ioFile;

    /**
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * UploaderPlugin constructor.
     *
     * @param S3Client $s3Client
     * @param Logger $logger
     * @param IoFile $ioFile
     * @param DirectoryList $directoryList
     */

    public function __construct(
        S3Client $s3Client,
        Logger $logger,
        IoFile $ioFile,
        DirectoryList $directoryList
    ) {
        $this->s3Client = $s3Client;
        $this->logger = $logger;
        $this->ioFile = $ioFile;
        $this->directoryList = $directoryList;
    }

    /**
     * After save plugin method to upload files to S3
     * 
     * @param Uploader $subject The uploader instance
     * @param array $result Upload result containing file info
     * @return array The original result array
     */
    public function afterSave(Uploader $subject, array $result): array
    {
        try {
            if (!$this->s3Client->isEnabled()) {
                return $result;
            }
            $this->logger->info("CloudCommerce_S3Aws: upload to S3");

            // Build absolute path and key
            $fileRel = ltrim($result['file'], '/');
            $mediaPath = $result['path'] ?? $this->directoryList->getPath('media');
            $absolute = rtrim($mediaPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileRel;

            // Normalize key: use "pub/media/..." relative path or just relative to media bucket
            // We'll use path relative to pub/media
            $mediaDir = $this->directoryList->getPath('media');
            $key = ltrim(str_replace($mediaDir, '', $absolute), '/\\');

            if ($this->ioFile->fileExists($absolute)) {
                $ok = $this->s3Client->uploadFile($absolute, $key);
                if ($ok) {
                    $this->logger->info("CloudCommerce_S3Aws: uploaded {$key} to S3");
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('CloudCommerce_S3Aws plugin error: ' . $e->getMessage());
        }

        return $result;
    }
}
