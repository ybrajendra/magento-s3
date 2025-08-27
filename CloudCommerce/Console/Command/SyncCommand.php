<?php
namespace CloudCommerce\S3Aws\Console\Command;

use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File as DriverFile;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use CloudCommerce\S3Aws\Model\S3client;
use Psr\Log\LoggerInterface;

class SyncCommand extends Command
{
    protected $state;
    protected $directoryList;
    protected $driverFile;
    protected $s3Client;
    protected $logger;

    public function __construct(
        State $state,
        DirectoryList $directoryList,
        DriverFile $driverFile,
        S3Client $s3Client,
        LoggerInterface $logger
    ) {
        $this->state = $state;
        $this->directoryList = $directoryList;
        $this->driverFile = $driverFile;
        $this->s3Client = $s3Client;
        $this->logger = $logger;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('cloudcommerce:s3:sync')
            ->setDescription('Sync pub/media -> S3 (uploads files not present in S3)');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->state->setAreaCode('crontab');
        } catch (\Exception $e) {
            // ignore if area code already set
        }

        if (!$this->s3Client->isEnabled()) {
            $output->writeln('<error>S3 is not enabled or misconfigured.</error>');
            return Cli::RETURN_FAILURE;
        }

        $mediaPath = $this->directoryList->getPath('media');
        $output->writeln("Scanning: {$mediaPath}");

        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($mediaPath));
        $count = 0;
        foreach ($rii as $file) {
            if ($file->isDir()) continue;
            $path = $file->getPathname();

            // skip hidden/system files
            if (strpos($path, '/.') !== false) continue;

            // Build key relative to pub/media
            $key = ltrim(str_replace($mediaPath, '', $path), '/\\');

            // try upload (S3 client handles overwrites)
            $ok = $this->s3Client->uploadFile($path, $key);
            if ($ok) {
                $count++;
                $output->writeln("Uploaded: {$key}");
            } else {
                $output->writeln("<comment>Failed: {$key}</comment>");
            }
        }
        $output->writeln("Done. Uploaded: {$count} files.");
        return Cli::RETURN_SUCCESS;
    }
}
