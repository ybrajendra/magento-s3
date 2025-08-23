<?php
/**
 * CloudCommerce S3 AWS Integration - Create Handler Plugin
 * 
 * This file contains the plugin for Magento's Product Gallery CreateHandler
 * to handle uploading product images to S3 after processing.
 */
namespace CloudCommerce\S3Aws\Plugin;

use CloudCommerce\S3Aws\Model\S3client;
use CloudCommerce\S3Aws\Logger\Logger;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Catalog\Model\Product\Media\Config;
use Magento\Framework\Filesystem;
use Magento\MediaStorage\Model\File\Uploader as FileUploader;
use Magento\Framework\Filesystem\Io\File as IoFile;

/**
 * Plugin for Magento\Catalog\Model\Product\Gallery\CreateHandler
 * 
 * This plugin intercepts the execute() method to upload product images to S3
 * after they are moved from tmp to their final location in media/catalog/product/.
 * It processes filename transformations and handles the upload of newly moved images.
 * 
 * Purpose: Ensures product gallery images are synced to S3 after being processed
 */
class CreateHandlerPlugin
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
     * @var Config
     */
    private $mediaConfig;
    
    /**
     * @var \Magento\Framework\Filesystem\Directory\WriteInterface
     */
    private $mediaDirectory;
    
    /**
     * @var IoFile
     */
    private $ioFile;

    public function __construct(
        S3client $s3Client,
        Logger $logger,
        DirectoryList $directoryList,
        Config $mediaConfig,
        Filesystem $filesystem,
        IoFile $ioFile
    ) {
        $this->s3Client = $s3Client;
        $this->logger = $logger;
        $this->directoryList = $directoryList;
        $this->mediaConfig = $mediaConfig;
        $this->ioFile = $ioFile;
        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
    }

    /**
     * After execute plugin method to upload product images to S3
     * 
     * @param \Magento\Catalog\Model\Product\Gallery\CreateHandler $subject
     * @param \Magento\Catalog\Model\Product $result The processed product
     * @param \Magento\Catalog\Model\Product $product The original product
     * @param array $arguments Additional arguments
     * @return \Magento\Catalog\Model\Product The processed product
     */
    public function afterExecute(\Magento\Catalog\Model\Product\Gallery\CreateHandler $subject, \Magento\Catalog\Model\Product $result, \Magento\Catalog\Model\Product $product, array $arguments = []): \Magento\Catalog\Model\Product
    {
        if (!$this->s3Client->isEnabled()) {
            return $result;
        }

        try {
            $attrCode = $subject->getAttribute()->getAttributeCode();
            $mediaGalleryData = $product->getData($attrCode);
            
            if (isset($mediaGalleryData['images']) && is_array($mediaGalleryData['images'])) {
                foreach ($mediaGalleryData['images'] as $image) {
                    if (isset($image['file']) && isset($image['new_file'])) {
                        // Process the filename like CreateHandler does
                        $file = $this->getFilenameFromTmp($this->getSafeFilename($image['file']));
                        $destination = $this->mediaConfig->getMediaPath($file);
                        $source = $this->mediaDirectory->getAbsolutePath($this->mediaConfig->getMediaPath($file));

                        if ($this->ioFile->fileExists($source)) {
                            $this->logger->info("Upload image to S3: " . $destination);
                            $success = $this->s3Client->uploadFile($source, $destination);
                            if ($success) {
                                $this->logger->info("Uploaded moved image to S3: " . $destination);
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('S3 CreateHandler upload error: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Get safe filename with proper directory separator
     * 
     * @param string $file The filename to process
     * @return string Safe filename
     */
    private function getSafeFilename(string $file): string
    {
        $file = DIRECTORY_SEPARATOR . ltrim($file, DIRECTORY_SEPARATOR);
        return $this->mediaDirectory->getDriver()->getRealPathSafety($file);
    }

    /**
     * Remove .tmp extension from filename if present
     * 
     * @param string $file The filename to process
     * @return string Filename without .tmp extension
     */
    private function getFilenameFromTmp(string $file): string
    {
        return strrpos($file, '.tmp') == strlen($file) - 4 ? substr($file, 0, strlen($file) - 4) : $file;
    }
}