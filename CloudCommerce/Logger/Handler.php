<?php
namespace CloudCommerce\S3Aws\Logger;

use Magento\Framework\Logger\Handler\Base as BaseHandler;

class Handler extends BaseHandler
{
    protected $fileName = '/var/log/s3aws_upload.log'; // Custom log file
    protected $loggerType = \Monolog\Level::Info;
}
