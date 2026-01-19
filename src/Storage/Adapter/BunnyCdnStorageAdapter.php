<?php

declare(strict_types=1);

namespace App\Storage\Adapter;

use App\Storage\AbstractStorageAdapter;
use App\Storage\DTO\ConnectionTestResult;
use App\Storage\DTO\StorageQuota;
use App\Storage\DTO\UploadResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * BunnyCDN Storage Adapter.
 * 
 * BunnyCDN использует собственный REST API, а не S3-совместимый.
 * Документация: https://docs.bunny.net/reference/storage-api
 */
class BunnyCdnStorageAdapter extends AbstractStorageAdapter
{
    private const REGIONS = [
        'de' => 'storage.bunnycdn.com',
        'ny' => 'ny.storage.bunnycdn.com',
        'la' => 'la.storage.bunnycdn.com',
        'sg' => 'sg.storage.bunnycdn.com',
        'syd' => 'syd.storage.bunnycdn.com',
        'uk' => 'uk.storage.bunnycdn.com',
        'se' => 'se.storage.bunnycdn.com',
        'br' => 'br.storage.bunnycdn.com',
        'jh' => 'jh.storage.bunnycdn.com',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $storageZone,
        private readonly string $apiKey,
        private readonly string $region = 'de',
        private readonly ?string $cdnUrl = null,
        private readonly int $timeout = 300,
    ) {}

    /**
     * Получить базовый URL для API.
     */
    private function getApiBaseUrl(): string
    {
        $host = self::REGIONS[$this->region] ?? self::REGIONS['de'];
        return "https://{$host}/{$this->storageZone}";
    }

    /**
     * Загрузить файл в BunnyCDN.
     */
    public function upload(string $localPath, string $remotePath): UploadResult
    {
        if (!file_exists($localPath)) {
            return UploadResult::failure("Local file not found: {$localPath}");
        }

        try {
            return $this->executeWithRetry(function () use ($localPath, $remotePath): UploadResult {
                $fileSize = filesize($localPath);
                $fileContent = file_get_contents($localPath);
                
                if ($fileContent === false) {
                    throw new \RuntimeException("Cannot read file: {$localPath}");
                }

                // Нормализуем путь
                $key = ltrim($remotePath, '/');
                $url = $this->getApiBaseUrl() . '/' . $key;

                $response = $this->httpClient->request('PUT', $url, [
                    'headers' => [
                        'AccessKey' => $this->apiKey,
                        'Content-Type' => 'application/octet-stream',
                    ],
                    'body' => $fileContent,
                    'timeout' => $this->timeout,
                ]);

                $statusCode = $response->getStatusCode();
                
                if ($statusCode >= 200 && $statusCode < 300) {
                    $this->logger?->info('BunnyCDN upload successful', [
                        'path' => $key,
                        'size' => $fileSize,
                    ]);
                    
                    return UploadResult::success(
                        remotePath: $key,
                        url: $this->getUrl($key),
                        fileSize: $fileSize ?: null
                    );
                }

                $content = $response->getContent(false);
                throw new \RuntimeException("BunnyCDN upload failed with status {$statusCode}: {$content}");
            }, 'upload');
        } catch (\Throwable $e) {
            $this->logger?->error('BunnyCDN upload failed', [
                'path' => $remotePath,
                'error' => $e->getMessage(),
            ]);
            return UploadResult::failure($e->getMessage());
        }
    }

    /**
     * Скачать файл из BunnyCDN.
     */
    public function download(string $remotePath, string $localPath): bool
    {
        try {
            return $this->executeWithRetry(function () use ($remotePath, $localPath): bool {
                $key = ltrim($remotePath, '/');
                $url = $this->getApiBaseUrl() . '/' . $key;

                $response = $this->httpClient->request('GET', $url, [
                    'headers' => [
                        'AccessKey' => $this->apiKey,
                    ],
                    'timeout' => $this->timeout,
                ]);

                $statusCode = $response->getStatusCode();
                
                if ($statusCode < 200 || $statusCode >= 300) {
                    throw new \RuntimeException("BunnyCDN download failed with status {$statusCode}");
                }

                $localDir = dirname($localPath);
                if (!is_dir($localDir)) {
                    mkdir($localDir, 0755, true);
                }

                $content = $response->getContent();
                $bytesWritten = file_put_contents($localPath, $content);
                
                if ($bytesWritten === false) {
                    throw new \RuntimeException("Failed to write file: {$localPath}");
                }

                return true;
            }, 'download');
        } catch (\Throwable $e) {
            $this->logger?->error('BunnyCDN download failed', [
                'path' => $remotePath,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }


    /**
     * Удалить файл из BunnyCDN.
     */
    public function delete(string $remotePath): bool
    {
        try {
            return $this->executeWithRetry(function () use ($remotePath): bool {
                $key = ltrim($remotePath, '/');
                $url = $this->getApiBaseUrl() . '/' . $key;

                $response = $this->httpClient->request('DELETE', $url, [
                    'headers' => [
                        'AccessKey' => $this->apiKey,
                    ],
                    'timeout' => $this->timeout,
                ]);

                $statusCode = $response->getStatusCode();
                
                // 200 = успех, 404 = файл не существует (считаем удалённым)
                return $statusCode >= 200 && $statusCode < 300 || $statusCode === 404;
            }, 'delete');
        } catch (\Throwable $e) {
            $this->logger?->error('BunnyCDN delete failed', [
                'path' => $remotePath,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Проверить существование файла.
     */
    public function exists(string $remotePath): bool
    {
        try {
            $key = ltrim($remotePath, '/');
            $url = $this->getApiBaseUrl() . '/' . $key;

            $response = $this->httpClient->request('HEAD', $url, [
                'headers' => [
                    'AccessKey' => $this->apiKey,
                ],
                'timeout' => 30,
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Получить публичный URL файла.
     */
    public function getUrl(string $remotePath): string
    {
        $key = ltrim($remotePath, '/');

        // Если настроен CDN URL (Pull Zone), используем его
        if ($this->cdnUrl !== null && $this->cdnUrl !== '') {
            return rtrim($this->cdnUrl, '/') . '/' . $key;
        }

        // Иначе используем прямой URL Storage Zone
        return $this->getApiBaseUrl() . '/' . $key;
    }

    /**
     * Получить подписанный URL.
     * BunnyCDN поддерживает Token Authentication для Pull Zone.
     */
    public function getSignedUrl(string $remotePath, int $expiresIn = 3600): string
    {
        // BunnyCDN Token Auth требует настройки на уровне Pull Zone
        // Пока возвращаем обычный URL
        return $this->getUrl($remotePath);
    }

    /**
     * Тест подключения к BunnyCDN.
     */
    public function testConnection(): ConnectionTestResult
    {
        $startTime = microtime(true);

        try {
            // Пробуем получить список файлов в корне Storage Zone
            $url = $this->getApiBaseUrl() . '/';
            
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'AccessKey' => $this->apiKey,
                ],
                'timeout' => 30,
            ]);

            $statusCode = $response->getStatusCode();
            $latencyMs = (microtime(true) - $startTime) * 1000;

            if ($statusCode >= 200 && $statusCode < 300) {
                return ConnectionTestResult::success(
                    message: "BunnyCDN connection successful. Storage Zone: {$this->storageZone}",
                    latencyMs: $latencyMs,
                    serverInfo: "Region: {$this->region}"
                );
            }

            $content = $response->getContent(false);
            return ConnectionTestResult::failure(
                "BunnyCDN returned status {$statusCode}: {$content}"
            );
        } catch (\Throwable $e) {
            return ConnectionTestResult::failure(
                "BunnyCDN connection failed: " . $e->getMessage()
            );
        }
    }

    /**
     * Получить информацию о квоте.
     * BunnyCDN API не предоставляет информацию о квоте напрямую.
     */
    public function getQuota(): ?StorageQuota
    {
        // BunnyCDN не предоставляет API для получения квоты
        // Можно было бы подсчитать через листинг, но это дорого
        return null;
    }

    /**
     * Создать директорию.
     * BunnyCDN создаёт директории автоматически при загрузке файлов.
     */
    public function createDirectory(string $path): bool
    {
        return true;
    }

    /**
     * Получить Storage Zone.
     */
    public function getStorageZone(): string
    {
        return $this->storageZone;
    }

    /**
     * Получить регион.
     */
    public function getRegion(): string
    {
        return $this->region;
    }

    /**
     * Получить CDN URL.
     */
    public function getCdnUrl(): ?string
    {
        return $this->cdnUrl;
    }
}
