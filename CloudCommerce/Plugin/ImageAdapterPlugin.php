<?php
/**
 * CloudCommerce S3 AWS Integration - Image Adapter Plugin
 * 
 * This file contains the plugin for Magento's Image Adapter interface
 * to handle uploading processed images to S3.
 */
namespace CloudCommerce\S3Aws\Plugin;

use CloudCommerce\S3Aws\Model\S3client;
use CloudCommerce\S3Aws\Logger\Logger;
use Magento\Framework\Filesystem\DirectoryList;

/**
 * Plugin for Magento\Framework\Image\Adapter\AdapterInterface
 * 
 * This plugin intercepts the save() method to upload processed images to S3
 * after they are saved to the local filesystem. It catches all image processing
 * operations including resizing, watermarking, and cache image generation.
 * 
 * Purpose: Ensures all processed images (including cache images) are synced to S3
 */
class ImageAdapterPlugin
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
     * After save plugin method to upload processed images to S3
     */
    public function afterSave(\Magento\Framework\Image\Adapter\AdapterInterface $subject, $result, ?string $destination = null, ?string $newName = null)
    {
        if (!$this->s3Client->isEnabled() || !$destination) {
            return $result;
        }

        try {
            if (file_exists($destination)) {
                $mediaPath = $this->directoryList->getPath('media');
                $relativePath = str_replace($mediaPath . '/', '', $destination);
                
                $success = $this->s3Client->uploadFile($destination, $relativePath);
                if ($success) {
                    $this->logger->info("Uploaded image to S3: " . $relativePath);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('S3 image adapter upload error: ' . $e->getMessage());
        }

        return $result;
    }
}