<?php

declare(strict_types=1);

namespace App\Storage\Adapter;

use App\Storage\AbstractStorageAdapter;
use App\Storage\DTO\ConnectionTestResult;
use App\Storage\DTO\StorageQuota;
use App\Storage\DTO\UploadResult;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

/**
 * S3-совместимый Storage Adapter для BunnyCDN, AWS S3, MinIO и других.
 * 
 * Поддерживает:
 * - BunnyCDN Storage (S3-совместимый API)
 * - AWS S3
 * - MinIO
 * - DigitalOcean Spaces
 * - Любые S3-совместимые хранилища
 */
class S3StorageAdapter extends AbstractStorageAdapter
{
    private S3Client $client;

    public function __construct(
        private readonly string $endpoint,
        private readonly string $region,
        private readonly string $bucket,
        private readonly string $accessKey,
        private readonly string $secretKey,
        private readonly ?string $cdnUrl = null,
        private readonly bool $pathStyleEndpoint = true,
        private readonly int $timeout = 60,
    ) {
        $this->initClient();
    }

    private function initClient(): void
    {
        $config = [
            'version' => 'latest',
            'region' => $this->region,
            'endpoint' => $this->endpoint,
            'use_path_style_endpoint' => $this->pathStyleEndpoint,
            'credentials' => [
                'key' => $this->accessKey,
                'secret' => $this->secretKey,
            ],
            'http' => [
                'timeout' => $this->timeout,
                'connect_timeout' => 10,
            ],
        ];

        $this->client = new S3Client($config);
    }

    /**
     * Загрузить файл в S3 хранилище.
     */
    public function upload(string $localPath, string $remotePath): UploadResult
    {
        if (!file_exists($localPath)) {
            return UploadResult::failure("Local file not found: {$localPath}");
        }

        try {
            return $this->executeWithRetry(function () use ($localPath, $remotePath): UploadResult {
                $fileSize = filesize($localPath);
                $mimeType = mime_content_type($localPath) ?: 'application/octet-stream';

                // Нормализуем путь (убираем начальный слеш)
                $key = ltrim($remotePath, '/');

                $result = $this->client->putObject([
                    'Bucket' => $this->bucket,
                    'Key' => $key,
                    'SourceFile' => $localPath,
                    'ContentType' => $mimeType,
                    'ACL' => 'public-read',
                ]);

                return UploadResult::success(
                    remotePath: $key,
                    url: $this->getUrl($key),
                    fileSize: $fileSize ?: null
                );
            }, 'upload');
        } catch (\Throwable $e) {
            return UploadResult::failure($e->getMessage());
        }
    }

    /**
     * Скачать файл из S3 хранилища.
     */
    public function download(string $remotePath, string $localPath): bool
    {
        try {
            return $this->executeWithRetry(function () use ($remotePath, $localPath): bool {
                $key = ltrim($remotePath, '/');

                $localDir = dirname($localPath);
                if (!is_dir($localDir)) {
                    mkdir($localDir, 0755, true);
                }

                $result = $this->client->getObject([
                    'Bucket' => $this->bucket,
                    'Key' => $key,
                    'SaveAs' => $localPath,
                ]);

                return file_exists($localPath);
            }, 'download');
        } catch (\Throwable) {
            return false;
        }
    }


    /**
     * Удалить файл из S3 хранилища.
     */
    public function delete(string $remotePath): bool
    {
        try {
            return $this->executeWithRetry(function () use ($remotePath): bool {
                $key = ltrim($remotePath, '/');

                $this->client->deleteObject([
                    'Bucket' => $this->bucket,
                    'Key' => $key,
                ]);

                return true;
            }, 'delete');
        } catch (\Throwable) {
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
            return $this->client->doesObjectExist($this->bucket, $key);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Получить публичный URL файла.
     * Использует CDN URL если настроен (для BunnyCDN).
     */
    public function getUrl(string $remotePath): string
    {
        $key = ltrim($remotePath, '/');

        // Если настроен CDN URL (BunnyCDN Pull Zone), используем его
        if ($this->cdnUrl !== null && $this->cdnUrl !== '') {
            return rtrim($this->cdnUrl, '/') . '/' . $key;
        }

        // Иначе формируем URL из endpoint
        return rtrim($this->endpoint, '/') . '/' . $this->bucket . '/' . $key;
    }

    /**
     * Получить подписанный URL с ограниченным временем жизни.
     */
    public function getSignedUrl(string $remotePath, int $expiresIn = 3600): string
    {
        try {
            $key = ltrim($remotePath, '/');

            $cmd = $this->client->getCommand('GetObject', [
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);

            $request = $this->client->createPresignedRequest($cmd, "+{$expiresIn} seconds");

            return (string) $request->getUri();
        } catch (\Throwable $e) {
            $this->logger?->error('Failed to generate signed URL', [
                'path' => $remotePath,
                'error' => $e->getMessage(),
            ]);
            
            // Fallback к обычному URL
            return $this->getUrl($remotePath);
        }
    }

    /**
     * Тест подключения к S3 хранилищу.
     */
    public function testConnection(): ConnectionTestResult
    {
        $startTime = microtime(true);

        try {
            // Пробуем получить список объектов (максимум 1)
            $result = $this->client->listObjectsV2([
                'Bucket' => $this->bucket,
                'MaxKeys' => 1,
            ]);

            $latencyMs = (microtime(true) - $startTime) * 1000;

            return ConnectionTestResult::success(
                message: "S3 connection successful. Bucket: {$this->bucket}",
                latencyMs: $latencyMs,
                serverInfo: "Endpoint: {$this->endpoint}"
            );
        } catch (AwsException $e) {
            return ConnectionTestResult::failure(
                "S3 connection failed: " . $e->getAwsErrorMessage()
            );
        } catch (\Throwable $e) {
            return ConnectionTestResult::failure(
                "S3 connection failed: " . $e->getMessage()
            );
        }
    }

    /**
     * Получить информацию о квоте хранилища.
     * Примечание: S3 API не предоставляет информацию о квоте напрямую.
     */
    public function getQuota(): ?StorageQuota
    {
        try {
            // Подсчитываем использованное место через листинг объектов
            $totalSize = 0;
            $continuationToken = null;

            do {
                $params = [
                    'Bucket' => $this->bucket,
                    'MaxKeys' => 1000,
                ];

                if ($continuationToken) {
                    $params['ContinuationToken'] = $continuationToken;
                }

                $result = $this->client->listObjectsV2($params);

                foreach ($result['Contents'] ?? [] as $object) {
                    $totalSize += $object['Size'] ?? 0;
                }

                $continuationToken = $result['NextContinuationToken'] ?? null;
            } while ($result['IsTruncated'] ?? false);

            // S3 не имеет лимита по умолчанию, возвращаем большое число
            return new StorageQuota(
                usedBytes: $totalSize,
                totalBytes: PHP_INT_MAX
            );
        } catch (\Throwable $e) {
            $this->logger?->warning('Failed to get S3 quota', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Создать директорию (в S3 директории создаются автоматически).
     */
    public function createDirectory(string $path): bool
    {
        // S3 не требует явного создания директорий
        return true;
    }

    /**
     * Получить endpoint.
     */
    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * Получить bucket.
     */
    public function getBucket(): string
    {
        return $this->bucket;
    }

    /**
     * Получить CDN URL.
     */
    public function getCdnUrl(): ?string
    {
        return $this->cdnUrl;
    }
}
