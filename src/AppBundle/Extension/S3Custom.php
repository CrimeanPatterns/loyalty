<?php

namespace AppBundle\Extension;

use AppBundle\Document\BaseDocument;
use Aws\S3\S3Client;
use Aws\Credentials\Credentials;
use Doctrine\Common\Persistence\ObjectRepository;
use Psr\Log\LoggerInterface;

class S3Custom
{

    private $bucket;
    /** @var LoggerInterface */
    private $logger;
    /** @var S3Client */
    private $client;

    public function __construct(S3Client $s3Client, $bucket, Loader $oldLoader, LoggerInterface $logger)
    {
        $this->bucket = $bucket;
        $this->client = $s3Client;
        $this->logger = $logger;
    }


    public function getBucket()
    {
        return $this->bucket;
    }


    public function uploadCheckerLogToBucket(string $requestId, string $logDir, array $accountFields, ObjectRepository $repo): void
    {
        $row = $repo->find($requestId);
        if (empty($row)) {
            return;
        }

        $cls = explode('\\', $repo->getClassName());
        $method = strtolower(array_pop($cls));

        $this->uploadLogToBucket($requestId, $accountFields['Code'], $method, $row->getPartner(), $logDir, $accountFields);
    }

    public function uploadLogToBucket(string $requestId, string $providerCode, string $method, string $partner, string $logDir, array $accountFields): void
    {
        $now = new \DateTime();
        $name = $partner . '_' . $method . '_' . $providerCode . '_' . $requestId . '_' . $now->format('Ymd_His_v');

        $this->uploadToBucket($name, $logDir, $accountFields);
    }


    public function uploadAutoLoginLog(string $requestId, string $logDir, string $partner, array $accountFields): void
    {
        $now = new \DateTime();
        $name = $partner . '_autologin_' . $accountFields['Code'] . '_' . $requestId . '_' . $now->format('Ymd_His_v');
        $this->uploadToBucket($name, $logDir, $accountFields);
    }


    private function uploadToBucket(string $name, string $logDir, array $accountFields): void
    {
        $logDirFiles = scandir($logDir);
        $files = [];
        foreach ($logDirFiles as $file) {
            if (in_array($file, ['.', '..']) || is_dir($logDir . '/' . $file)) {
                continue;
            }
            $files[] = $logDir . '/' . $file;
        }

        // begin: mask Private Info
        $maskText = [];
        if (isset($accountFields['Pass'])) {
            $maskText[$accountFields['Pass']] = '**PASSWORD**';
            $maskText[htmlentities($accountFields['Pass'])] = '**PASSWORD**';
        }
        foreach (array('Login', 'Login2', 'CardNumber') as $field) {
            if (isset($accountFields[$field]) && preg_match('/^\d{15,16}$/ims', $accountFields[$field])) {
                $maskText[$accountFields[$field]] = '**CC_' . $field . '_' . substr($accountFields[$field], 12,
                        4) . '**';
            }
        }
        if (isset($accountFields['SecurityNumber'])) {
            $maskText[$accountFields['SecurityNumber']] = '***';
        }
        if (count($maskText) > 0) {
            foreach (array_merge(array($logDir . '/log.html'), $files) as $file) {
                if (file_exists($file)) {
                    $text = file_get_contents($file);
                    foreach ($maskText as $search => $replace) {
                        $text = str_replace($search, $replace, $text);
                    }
                    file_put_contents($file, $text);
                }
            }
        }
        // end: mask Private Info

        $zipFilename = \TAccountChecker::ArchiveLogsToZip(file_get_contents($logDir . '/log.html'), $name, $files);

        $result = $this->client->upload($this->bucket, basename($zipFilename), file_get_contents($zipFilename), 'bucket-owner-full-control');

        if ($result && file_exists($zipFilename)) {
            unlink($zipFilename);
        }

        $this->logger->info('logs uploaded to ' . basename($zipFilename));
    }


    public function listFiles($prefix)
    {
        $result = $this->client->listObjects(['Bucket' => $this->bucket, 'Prefix' => $prefix]);
        if (empty($result['Contents'])) {
            return [];
        }
        return $result['Contents'];
    }

    public function getLog(string $filename) : string
    {
        $result = $this->client->getObject(['Bucket' => $this->bucket, 'Key' => $filename]);
        return (string) $result['Body'];
    }

}