<?php

declare(strict_types=1);

namespace App\Storage\Factory;

use App\Entity\Storage;
use App\Storage\Adapter\LocalStorageAdapter;
use App\Storage\StorageAdapterInterface;

/**
 * Factory for creating LocalStorageAdapter instances from Storage entity configuration.
 * 
 * Validates: Requirements 2.1
 */
class LocalStorageAdapterFactory implements StorageAdapterFactoryInterface
{
    public function __construct(
        private readonly string $projectDir,
    ) {}

    /**
     * Check if this factory supports the given storage type.
     */
    public function supports(Storage $storage): bool
    {
        return $storage->getType() === Storage::TYPE_LOCAL;
    }

    /**
     * Create LocalStorageAdapter from Storage entity configuration.
     * 
     * Expected config structure:
     * {
     *   "basePath": "/var/www/public/media",
     *   "publicUrl": "/media"
     * }
     * 
     * @throws \InvalidArgumentException If required config fields are missing
     */
    public function create(Storage $storage): StorageAdapterInterface
    {
        if (!$this->supports($storage)) {
            throw new \InvalidArgumentException(
                \sprintf('LocalStorageAdapterFactory does not support storage type: %s', $storage->getType())
            );
        }

        $config = $storage->getConfig();
        
        $this->validateConfig($config);

        $basePath = $config['basePath'];
        
        // If basePath is relative, make it absolute using project directory
        if (!$this->isAbsolutePath($basePath)) {
            $basePath = $this->projectDir . '/' . ltrim($basePath, '/');
        }

        return new LocalStorageAdapter(
            basePath: $basePath,
            publicUrl: $config['publicUrl'] ?? '',
        );
    }

    /**
     * Validate that all required configuration fields are present.
     * 
     * @throws \InvalidArgumentException If required fields are missing or empty
     */
    private function validateConfig(array $config): void
    {
        if (!isset($config['basePath']) || $config['basePath'] === '') {
            throw new \InvalidArgumentException('Missing required Local storage configuration field: basePath');
        }
    }

    /**
     * Check if a path is absolute.
     */
    private function isAbsolutePath(string $path): bool
    {
        // Unix absolute path
        if (str_starts_with($path, '/')) {
            return true;
        }
        
        // Windows absolute path (e.g., C:\, D:\)
        if (preg_match('/^[a-zA-Z]:[\\\\\/]/', $path)) {
            return true;
        }
        
        return false;
    }
}
