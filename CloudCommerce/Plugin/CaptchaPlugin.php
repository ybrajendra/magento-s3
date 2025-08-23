<?php
/**
 * CloudCommerce S3 AWS Integration - Captcha Plugin
 * 
 * This file contains the plugin for Magento's Captcha model to handle
 * uploading captcha images to S3 after generation.
 */
namespace CloudCommerce\S3Aws\Plugin;

use CloudCommerce\S3Aws\Model\S3client;
use CloudCommerce\S3Aws\Logger\Logger;
use Magento\Framework\Filesystem\DirectoryList;

/**
 * Plugin for Magento\Captcha\Model\DefaultModel
 * 
 * This plugin intercepts the generate() method to upload captcha images to S3
 * after they are generated. Works for both admin and frontend areas by using
 * the captcha model's getImgDir() method to get the correct directory path.
 * 
 * Purpose: Ensures captcha images are available from S3 for display
 */
class CaptchaPlugin
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
     * After generate plugin method to upload captcha images to S3
     * 
     * @param \Magento\Captcha\Model\DefaultModel $subject The captcha model
     * @param string $result The generated captcha result
     * @return string The original result
     */
    public function afterGenerate(\Magento\Captcha\Model\DefaultModel $subject, string $result): string
    {
        if (!$this->s3Client->isEnabled()) {
            return $result;
        }

        try {
            $captchaDir = $subject->getImgDir();
            $captchaId = $subject->getId();
            $imagePath = $captchaDir . $captchaId . '.png';
            
            if (file_exists($imagePath)) {
                $mediaPath = $this->directoryList->getPath('media');
                $relativePath = str_replace($mediaPath . '/', '', $imagePath);
                
                $success = $this->s3Client->uploadFile($imagePath, $relativePath);
                if ($success) {
                    $this->logger->info("Uploaded captcha image to S3: " . $relativePath);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('S3 Captcha upload error: ' . $e->getMessage());
        }

        return $result;
    }
}