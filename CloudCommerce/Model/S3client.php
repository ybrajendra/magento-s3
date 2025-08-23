<?php
/**
 * CloudCommerce S3 AWS Integration
 * 
 * This file contains the main S3 client class for handling AWS S3 operations
 * including file uploads and S3 client initialization.
 */
namespace CloudCommerce\S3Aws\Model;

use Aws\S3\S3Client as AwsS3Client;
use CloudCommerce\S3Aws\Logger\Logger;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * S3 Client Model
 * 
 * Handles AWS S3 client initialization and file upload operations.
 * Manages S3 configuration from Magento admin settings and provides
 * methods for uploading files to S3 bucket.
 */
class S3client
{
    /**
     * @var AwsS3Client|null
     */
    private $client;

    /**
     * @var string
     * This is the S3 bucket name configured in the system.
     */
    private $bucket;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var ScopeConfigInterface
     * This is used to get configuration values from the system.
     */
    private $scopeConfig;

    const XML_PATH_ENABLED = 'cloudcommerce_s3aws/general/enabled';
    const XML_PATH_AWS_KEY = 'cloudcommerce_s3aws/general/aws_key';
    const XML_PATH_AWS_SECRET = 'cloudcommerce_s3aws/general/aws_secret';
    const XML_PATH_REGION = 'cloudcommerce_s3aws/general/region';
    const XML_PATH_BUCKET = 'cloudcommerce_s3aws/general/bucket';
    const XML_PATH_BASE_FOLDER = 'cloudcommerce_s3aws/general/base_folder';

    /**
     * S3client constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param Logger $logger
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Logger $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->initClient();
    }

    /**
     * Initialize AWS S3 client with configuration from Magento admin
     * 
     * @return void
     */
    protected function initClient(): void
    {
        $enabled = $this->scopeConfig->getValue(self::XML_PATH_ENABLED);
        if (!$enabled) {
            return;
        }

        $key = $this->scopeConfig->getValue(self::XML_PATH_AWS_KEY);
        $secret = $this->scopeConfig->getValue(self::XML_PATH_AWS_SECRET);
        $region = $this->scopeConfig->getValue(self::XML_PATH_REGION);
        $bucket = $this->scopeConfig->getValue(self::XML_PATH_BUCKET);

        if (!$key || !$secret || !$region || !$bucket) {
            $this->logger->warning('CloudCommerce_S3Aws: incomplete S3 config');
            return;
        }

        $this->bucket = $bucket;
        try {
            $this->client = new AwsS3Client([
                'version' => 'latest',
                'region' => $region,
                'credentials' => [
                    'key' => $key,
                    'secret' => $secret
                ],
            ]);
        } catch (\Exception $e) {
            $this->logger->error('CloudCommerce_S3Aws: S3 client init error: ' . $e->getMessage());
            $this->client = null;
        }
    }

    /**
     * Check if S3 client is enabled and properly initialized
     * 
     * @return bool
     */
    public function isEnabled(): bool
    {
        return (bool)$this->client;
    }

    /**
     * Upload a local file to S3 bucket
     * 
     * @param string $localPath Full filesystem path to the local file
     * @param string $key Relative path/key inside S3 bucket
     * @return bool True if upload successful, false otherwise
     */
    public function uploadFile(string $localPath, string $key): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }
        try {
            $baseFolder = $this->scopeConfig->getValue(
                self::XML_PATH_BASE_FOLDER,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );

            // Build S3 key
            $objectKey = ltrim($key, '/'); // e.g. catalog/product/b/a/banner.jpg

            if (!empty($baseFolder)) {
                $baseFolder = trim($baseFolder);
                $objectKey = rtrim($baseFolder, '/') . '/' . $objectKey;
            }

            $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $objectKey,
                'SourceFile' => $localPath
            ]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('CloudCommerce_S3Aws: upload error for ' . $key . ' - ' . $e->getMessage());
            return false;
        }
    }
}
