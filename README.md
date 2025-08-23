# CloudCommerce_S3Aws

A Magento 2 module that automatically syncs media files to Amazon S3, providing seamless integration between Magento's media storage and AWS S3 bucket.

## Features

- **Automatic File Upload**: Syncs all uploaded files to S3 automatically
- **Product Images**: Handles product gallery images, thumbnails, and cache images
- **Category Images**: Syncs category images to S3
- **Swatch Images**: Uploads attribute swatch images
- **Captcha Images**: Syncs captcha images for both admin and frontend
- **Cache Image Generation**: Creates product image cache files on-demand
- **Comprehensive Logging**: Detailed logging for all S3 operations

## Requirements

- Magento 2.4+
- PHP 8.0+
- AWS SDK for PHP (composer command composer require aws/aws-sdk-php)
- Valid AWS S3 bucket with appropriate permissions

## Installation

1. Copy the module to `app/code/CloudCommerce/S3Aws/`
2. Run Magento commands:
   ```bash
   php bin/magento module:enable CloudCommerce_S3Aws
   php bin/magento setup:upgrade
   php bin/magento cache:flush
   ```

## Configuration

Navigate to **Admin Panel > Stores > Configuration > CloudCommerce > S3 AWS** and configure:

- **Enable**: Enable/disable the module
- **AWS Access Key**: Your AWS access key
- **AWS Secret Key**: Your AWS secret key
- **Region**: AWS region (e.g., us-east-1)
- **Bucket Name**: S3 bucket name
- **Base Folder**: Optional folder prefix in S3 bucket

## Architecture

### Core Components

#### S3Client Model
- **File**: `Model/S3client.php`
- **Purpose**: Handles AWS S3 client initialization and file upload operations
- **Methods**: `isEnabled()`, `uploadFile()`

#### Plugin System
The module uses Magento's plugin system to intercept file upload operations.

### File Upload Flow

1. **File Upload**: User uploads file through Magento admin
2. **Tmp Storage**: File saved to local tmp directory
3. **Plugin Intercept**: UploaderPlugin uploads to S3
4. **File Processing**: Magento processes/moves file to final location
5. **Final Upload**: Respective plugins upload processed files to S3
6. **Cache Generation**: AssetImagePlugin creates cache images on-demand

## CLI Commands

### Sync Existing Files
```bash
php bin/magento cloudcommerce:s3:sync
```
Syncs existing media files to S3 bucket.

## Logging

All S3 operations are logged to `var/log/s3_upload.log` including:
- Successful uploads with file paths
- Error messages with detailed information
- Configuration warnings

## Troubleshooting

### Common Issues

1. **Images not uploading to S3**
   - Check AWS credentials and permissions
   - Verify S3 bucket exists and is accessible
   - Check logs in `var/log/s3_upload.log`

2. **Cache images not displaying**
   - Ensure AssetImagePlugin is enabled
   - Check original product images exist locally
   - Verify image processing permissions

3. **Configuration not saving**
   - Clear cache after configuration changes
   - Check admin user permissions

### Debug Mode
Enable detailed logging by checking the log file:
```bash
tail -f var/log/s3_upload.log
```

## SEO Keywords

Magento 2 S3 integration, AWS S3 Magento module, Magento media storage S3, Amazon S3 file sync, Magento cloud storage, S3 image upload, Magento CDN integration, AWS S3 media sync, Magento file storage, S3 bucket integration, Magento image optimization, cloud media storage, Magento AWS integration, S3 automatic upload, Magento performance optimization, Magento S3 plugin, AWS media management, Magento cache images S3, S3 file synchronization, Magento media CDN