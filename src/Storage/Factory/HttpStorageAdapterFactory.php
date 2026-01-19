<?php

declare(strict_types=1);

namespace App\Storage\Factory;

use App\Entity\Storage;
use App\Storage\Adapter\HttpStorageAdapter;
use App\Storage\StorageAdapterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Factory for creating HttpStorageAdapter instances from Storage entity configuration.
 * 
 * Validates: Requirements 1.4
 */
class HttpStorageAdapterFactory implements StorageAdapterFactoryInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {}

    /**
     * Check if this factory supports the given storage type.
     */
    public function supports(Storage $storage): bool
    {
        return $storage->getType() === Storage::TYPE_HTTP;
    }

    /**
     * Create HttpStorageAdapter from Storage entity configuration.
     * 
     * Expected config structure:
     * {
     *   "baseUrl": "https://storage.example.com",
     *   "uploadEndpoint": "/api/upload",
     *   "deleteEndpoint": "/api/delete",
     *   "authToken": "Bearer token123",
     *   "authHeader": "Authorization",
     *   "timeout": 60,
     *   "downloadEndpoint": "/api/download",
     *   "existsEndpoint": "/api/exists",
     *   "quotaEndpoint": "/api/quota"
     * }
     * 
     * @throws \InvalidArgumentException If required config fields are missing
     */
    public function create(Storage $storage): StorageAdapterInterface
    {
        if (!$this->supports($storage)) {
            throw new \InvalidArgumentException(
                \sprintf('HttpStorageAdapterFactory does not support storage type: %s', $storage->getType())
            );
        }

        $config = $storage->getConfig();
        
        $this->validateConfig($config);

        return new HttpStorageAdapter(
            httpClient: $this->httpClient,
            baseUrl: $config['baseUrl'],
            uploadEndpoint: $config['uploadEndpoint'],
            deleteEndpoint: $config['deleteEndpoint'],
            authToken: $config['authToken'],
            authHeader: $config['authHeader'] ?? 'Authorization',
            timeout: (int) ($config['timeout'] ?? 60),
            downloadEndpoint: $config['downloadEndpoint'] ?? null,
            existsEndpoint: $config['existsEndpoint'] ?? null,
            quotaEndpoint: $config['quotaEndpoint'] ?? null,
        );
    }

    /**
     * Validate that all required configuration fields are present.
     * 
     * Requirement 1.4: WHEN an administrator creates a new Remote Server storage THEN the System 
     * SHALL require base URL, authentication token, and upload endpoint fields
     * 
     * @throws \InvalidArgumentException If required fields are missing or empty
     */
    private function validateConfig(array $config): void
    {
        $requiredFields = ['baseUrl', 'authToken', 'uploadEndpoint'];
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (!isset($config[$field]) || $config[$field] === '') {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            throw new \InvalidArgumentException(
                \sprintf('Missing required HTTP configuration fields: %s', implode(', ', $missingFields))
            );
        }

        // Validate baseUrl format
        if (!filter_var($config['baseUrl'], FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException(
                \sprintf('Invalid HTTP base URL: %s', $config['baseUrl'])
            );
        }

        // Validate timeout if provided
        if (isset($config['timeout'])) {
            $timeout = (int) $config['timeout'];
            if ($timeout < 1) {
                throw new \InvalidArgumentException(
                    \sprintf('Invalid HTTP timeout: %d. Timeout must be positive', $timeout)
                );
            }
        }

        // Validate deleteEndpoint is present (required for full functionality)
        if (!isset($config['deleteEndpoint']) || $config['deleteEndpoint'] === '') {
            throw new \InvalidArgumentException(
                'Missing required HTTP configuration field: deleteEndpoint'
            );
        }
    }
}
