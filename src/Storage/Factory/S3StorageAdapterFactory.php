<?php

declare(strict_types=1);

namespace App\Storage\Factory;

use App\Entity\Storage;
use App\Storage\Adapter\BunnyCdnStorageAdapter;
use App\Storage\Adapter\S3StorageAdapter;
use App\Storage\StorageAdapterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Factory для создания S3/BunnyCDN адаптеров из конфигурации Storage entity.
 * 
 * Автоматически определяет BunnyCDN по endpoint и создаёт соответствующий адаптер.
 */
class S3StorageAdapterFactory implements StorageAdapterFactoryInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {}

    /**
     * Проверить поддержку типа хранилища.
     */
    public function supports(Storage $storage): bool
    {
        return $storage->getType() === Storage::TYPE_S3;
    }

    /**
     * Создать адаптер из конфигурации Storage entity.
     * 
     * Для BunnyCDN создаёт BunnyCdnStorageAdapter.
     * Для других S3-совместимых хранилищ создаёт S3StorageAdapter.
     */
    public function create(Storage $storage): StorageAdapterInterface
    {
        if (!$this->supports($storage)) {
            throw new \InvalidArgumentException(
                \sprintf('S3StorageAdapterFactory does not support storage type: %s', $storage->getType())
            );
        }

        $config = $storage->getConfig();
        
        $this->validateConfig($config);

        // Определяем BunnyCDN по endpoint
        $endpoint = $config['endpoint'] ?? '';
        if ($this->isBunnyCdnEndpoint($endpoint)) {
            return $this->createBunnyCdnAdapter($config);
        }

        // Для других S3-совместимых хранилищ
        return $this->createS3Adapter($config);
    }

    /**
     * Проверить, является ли endpoint BunnyCDN.
     */
    private function isBunnyCdnEndpoint(string $endpoint): bool
    {
        return str_contains($endpoint, 'bunnycdn.com') || str_contains($endpoint, 'bunny.net');
    }

    /**
     * Создать BunnyCDN адаптер.
     */
    private function createBunnyCdnAdapter(array $config): BunnyCdnStorageAdapter
    {
        return new BunnyCdnStorageAdapter(
            httpClient: $this->httpClient,
            storageZone: $config['bucket'],
            apiKey: $config['secretKey'],
            region: $config['region'] ?? 'de',
            cdnUrl: !empty($config['cdnUrl']) ? $config['cdnUrl'] : null,
            timeout: (int) ($config['timeout'] ?? 300),
        );
    }

    /**
     * Создать S3 адаптер.
     */
    private function createS3Adapter(array $config): S3StorageAdapter
    {
        return new S3StorageAdapter(
            endpoint: $config['endpoint'],
            region: $config['region'] ?? 'us-east-1',
            bucket: $config['bucket'],
            accessKey: $config['accessKey'],
            secretKey: $config['secretKey'],
            cdnUrl: !empty($config['cdnUrl']) ? $config['cdnUrl'] : null,
            pathStyleEndpoint: (bool) ($config['pathStyleEndpoint'] ?? true),
            timeout: (int) ($config['timeout'] ?? 60),
        );
    }

    /**
     * Валидация конфигурации.
     */
    private function validateConfig(array $config): void
    {
        $requiredFields = ['endpoint', 'bucket', 'secretKey'];
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (!isset($config[$field]) || $config[$field] === '') {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            throw new \InvalidArgumentException(
                \sprintf('Missing required S3/BunnyCDN configuration fields: %s', implode(', ', $missingFields))
            );
        }

        // Валидация endpoint URL
        if (!filter_var($config['endpoint'], FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException(
                \sprintf('Invalid endpoint URL: %s', $config['endpoint'])
            );
        }

        // Валидация CDN URL если указан
        if (isset($config['cdnUrl']) && $config['cdnUrl'] !== '') {
            if (!filter_var($config['cdnUrl'], FILTER_VALIDATE_URL)) {
                throw new \InvalidArgumentException(
                    \sprintf('Invalid CDN URL: %s', $config['cdnUrl'])
                );
            }
        }
    }
}
