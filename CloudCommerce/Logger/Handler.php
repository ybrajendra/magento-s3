<?php
namespace CloudCommerce\S3Aws\Logger;

use Magento\Framework\Logger\Handler\Base as BaseHandler;
use Magento\Framework\Filesystem\DriverInterface;

class Handler extends BaseHandler
{
    protected $fileName = '/var/log/s3aws_upload.log'; // Custom log file
    
    public function __construct(DriverInterface $filesystem)
    {
        // Compatibility for both Magento 2.4.6 (Monolog 2.x) and 2.4.8 (Monolog 3.x)
        $this->loggerType = class_exists('\Monolog\Level') 
            ? \Monolog\Level::Info 
            : \Monolog\Logger::INFO;
        
        parent::__construct($filesystem);
    }
}
