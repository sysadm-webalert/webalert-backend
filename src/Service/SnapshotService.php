<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class SnapshotService
{
    private $targetDirectory;
    private HttpClientInterface $httpClient;
    private $puppeteerBaseUrl;

    public function __construct($targetDirectory, HttpClientInterface $httpClient, string $puppeteerBaseUrl)
    {
        $this->targetDirectory = $targetDirectory;
        $this->httpClient = $httpClient;
        $this->puppeteerBaseUrl = $puppeteerBaseUrl;
    }

    public function captureSnapshot(string $url, string $client): ?string
    {
        try {
            $sanitizedUrl = preg_replace('/[^a-zA-Z0-9-_]/', '_', parse_url($url, PHP_URL_HOST));
            $fileName = sprintf('%s-%s.jpg', $sanitizedUrl, $client);

            $filePath = $this->getTargetDirectory() . DIRECTORY_SEPARATOR . $fileName;

            $response = $this->httpClient->request('GET', $this->puppeteerBaseUrl . '/snapshot', [
                'query' => ['url' => $url],
                'headers' => ['Accept' => 'image/jpeg'],
            ]);

            if ($response->getStatusCode() === 200) {
                $fileContent = $response->getContent();

                file_put_contents($filePath, $fileContent);

                return $fileName;
            }
        } catch (\Exception $e) {
            throw new \RuntimeException('Error capturing snapshot: ' . $e->getMessage());
        }

        return null;
    }

    public function deleteSnapshot(string $url, string $client): bool
    {
        $sanitizedUrl = preg_replace('/[^a-zA-Z0-9-_]/', '_', parse_url($url, PHP_URL_HOST));
        $fileName = sprintf('%s-%s.jpg', $sanitizedUrl, $client);

        $filePath = $this->getTargetDirectory() . DIRECTORY_SEPARATOR . $fileName;

        if (file_exists($filePath)) {
            return unlink($filePath);
        }

        return false;
    }

    public function getTargetDirectory(): string
    {
        return $this->targetDirectory;
    }
}
