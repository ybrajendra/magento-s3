<?php
/**
 * CloudCommerce S3 AWS Integration - Image Uploader Plugin
 * 
 * This file contains the plugin for Magento's ImageUploader class
 * to handle uploading category and other images to S3.
 */
namespace CloudCommerce\S3Aws\Plugin;

use CloudCommerce\S3Aws\Model\S3client;
use CloudCommerce\S3Aws\Logger\Logger;
use Magento\Framework\Filesystem\DirectoryList;

/**
 * Plugin for Magento\Catalog\Model\ImageUploader
 * 
 * This plugin intercepts the moveFileFromTmp() method to upload category images
 * to S3 after they are moved from tmp to their final location. It handles both
 * category images and other images that use the ImageUploader class.
 * 
 * Purpose: Ensures category and other uploaded images are synced to S3
 */
class ImageUploaderPlugin
{
    /**
     * @var S3client
     */
    private $s3Client;
    
    /**
     * @var Logger
     */
    private $logger;
    
    /**
     * @var DirectoryList
     */
    private $directoryList;

    public function __construct(
        S3client $s3Client,
        Logger $logger,
        DirectoryList $directoryList
    ) {
        $this->s3Client = $s3Client;
        $this->logger = $logger;
        $this->directoryList = $directoryList;
    }

    /**
     * After moveFileFromTmp plugin method to upload images to S3
     * 
     * @param \Magento\Catalog\Model\ImageUploader $subject
     * @param string $result The moved file result
     * @param string $imageName The original image name
     * @return string The file result
     */
    public function afterMoveFileFromTmp(\Magento\Catalog\Model\ImageUploader $subject, string $result, string $imageName): string
    {
        if (!$this->s3Client->isEnabled()) {
            return $result;
        }

        try {
            $mediaPath = $this->directoryList->getPath('media');
            $basePath = $subject->getBasePath();
            $absolutePath = $mediaPath . '/' . $result;
            if (file_exists($absolutePath)) {
                $relativePath = $result;

                $success = $this->s3Client->uploadFile($absolutePath, $relativePath);
                if ($success) {
                    $imageType = strpos($relativePath, 'swatch') !== false ? 'swatch' : 'category';
                    $this->logger->info("Uploaded {$imageType} image to S3: " . $relativePath);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('S3 ImageUploader upload error: ' . $e->getMessage());
        }

        return $result;
    }
}