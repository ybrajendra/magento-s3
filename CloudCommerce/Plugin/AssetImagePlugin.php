<?php
/**
 * CloudCommerce S3 AWS Integration - Asset Image Plugin
 * 
 * This file contains the plugin for Magento's Asset Image class to handle
 * on-demand cache image generation for product images.
 */
namespace CloudCommerce\S3Aws\Plugin;

use CloudCommerce\S3Aws\Model\S3client;
use CloudCommerce\S3Aws\Logger\Logger;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Catalog\Model\Product\Media\Config;
use Magento\Framework\Filesystem;
use Magento\Framework\Image\AdapterFactory;

/**
 * Plugin for Magento\Catalog\Model\View\Asset\Image
 * 
 * This plugin intercepts the getUrl() method to create product image cache files on-demand.
 * When a cache image doesn't exist, it creates the resized image directly using the original
 * product image and Magento's image processing system.
 * 
 * Purpose: Ensures cache images are available for S3 upload by other plugins
 */
class AssetImagePlugin
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
    
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    
    /**
     * @var Config
     */
    private $mediaConfig;
    
    /**
     * @var \Magento\Framework\Filesystem\Directory\WriteInterface
     */
    private $mediaDirectory;
    
    /**
     * @var AdapterFactory
     */
    private $imageFactory;

    public function __construct(
        S3client $s3Client,
        Logger $logger,
        DirectoryList $directoryList,
        Config $mediaConfig,
        StoreManagerInterface $storeManager,
        Filesystem $filesystem,
        AdapterFactory $imageFactory
    ) {
        $this->s3Client = $s3Client;
        $this->logger = $logger;
        $this->directoryList = $directoryList;
        $this->mediaConfig = $mediaConfig;
        $this->storeManager = $storeManager;
        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $this->imageFactory = $imageFactory;
    }

    /**
     * After getUrl plugin method to create cache images on-demand
     * 
     * @param \Magento\Catalog\Model\View\Asset\Image $subject
     * @param string $result The original URL result
     * @return string The processed URL result
     */
    public function afterGetUrl(\Magento\Catalog\Model\View\Asset\Image $subject, string $result): string
    {
        if (!$this->s3Client->isEnabled()) {
            return $result;
        }

        try {
            $imagePath = $subject->getPath();

            // If cache image doesn't exist, create it directly
            if ($imagePath && !file_exists($imagePath) && strpos($imagePath, 'cache') !== false) {
                $sourceFile = $subject->getSourceFile();
                $mediaPath = $this->directoryList->getPath('media');

                $sourceFile = $mediaPath. "/". $sourceFile;
                
                if ($sourceFile && file_exists($sourceFile)) {
                    // Create directory if it doesn't exist
                    $imageDir = dirname($imagePath);
                    if (!is_dir($imageDir)) {
                        mkdir($imageDir, 0755, true);
                    }
                    
                    // Get image dimensions from misc params
                    $miscParams = $subject->getImageTransformationParameters();

                    $width = $miscParams['width'] ?? null;
                    $height = $miscParams['height'] ?? null;
                    
                    if ($width && $height) {
                        // Create resized image
                        $image = $this->imageFactory->create();
                        $image->open($sourceFile);
                        $image->keepAspectRatio(true);
                        $image->resize($width, $height);
                        $image->save($imagePath);
                    }
                }
                
            }
        } catch (\Exception $e) {
            $this->logger->error('S3 AssetImage upload error: ' . $e->getMessage());
        }
        return $result;
    }
}