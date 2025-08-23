<?php
/**
 * CloudCommerce S3 AWS Integration - Swatch Helper Plugin
 * 
 * This file contains the plugin for Magento's Swatches Helper Media class
 * to handle uploading swatch images to S3.
 */
namespace CloudCommerce\S3Aws\Plugin;

use CloudCommerce\S3Aws\Model\S3client;
use CloudCommerce\S3Aws\Logger\Logger;
use Magento\Framework\Filesystem\DirectoryList;

/**
 * Plugin for Magento\Swatches\Helper\Media
 * 
 * This plugin intercepts the moveImageFromTmp() method to upload swatch images
 * to S3 after they are moved from tmp to their final location in media/attribute/swatch/.
 * It handles attribute swatch images uploaded from the admin panel.
 * 
 * Purpose: Ensures swatch images are synced to S3 for product attribute swatches
 */
class SwatchHelperPlugin
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
     * After moveImageFromTmp plugin method to upload swatch images to S3
     */
    public function afterMoveImageFromTmp(\Magento\Swatches\Helper\Media $subject, $result, $file): string
    {
        if (!$this->s3Client->isEnabled()) {
            return $result;
        }

        try {
            $mediaPath = $this->directoryList->getPath('media');
            $absolutePath = $mediaPath . '/attribute/swatch' . $result;

            if (file_exists($absolutePath)) {
                $relativePath = 'attribute/swatch' . $result;
                
                $success = $this->s3Client->uploadFile($absolutePath, $relativePath);
                if ($success) {
                    $this->logger->info("Uploaded swatch image to S3: " . $relativePath);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('S3 SwatchHelper upload error: ' . $e->getMessage());
        }

        return $result;
    }
}