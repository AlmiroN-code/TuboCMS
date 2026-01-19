<?php

declare(strict_types=1);

namespace App\Storage\Factory;

use App\Entity\Storage;
use App\Storage\Adapter\FtpStorageAdapter;
use App\Storage\StorageAdapterInterface;

/**
 * Factory for creating FtpStorageAdapter instances from Storage entity configuration.
 * 
 * Validates: Requirements 1.2
 */
class FtpStorageAdapterFactory implements StorageAdapterFactoryInterface
{
    /**
     * Check if this factory supports the given storage type.
     */
    public function supports(Storage $storage): bool
    {
        return $storage->getType() === Storage::TYPE_FTP;
    }

    /**
     * Create FtpStorageAdapter from Storage entity configuration.
     * 
     * Expected config structure:
     * {
     *   "host": "ftp.example.com",
     *   "port": 21,
     *   "username": "user",
     *   "password": "pass",
     *   "basePath": "/uploads",
     *   "passive": true,
     *   "ssl": false,
     *   "timeout": 30
     * }
     * 
     * @throws \InvalidArgumentException If required config fields are missing
     */
    public function create(Storage $storage): StorageAdapterInterface
    {
        if (!$this->supports($storage)) {
            throw new \InvalidArgumentException(
                sprintf('FtpStorageAdapterFactory does not support storage type: %s', $storage->getType())
            );
        }

        $config = $storage->getConfig();
        
        $this->validateConfig($config);

        return new FtpStorageAdapter(
            host: $config['host'],
            port: (int) ($config['port'] ?? 21),
            username: $config['username'],
            password: $config['password'],
            basePath: $config['basePath'],
            passive: (bool) ($config['passive'] ?? true),
            ssl: (bool) ($config['ssl'] ?? false),
            timeout: (int) ($config['timeout'] ?? 30),
        );
    }

    /**
     * Validate that all required configuration fields are present.
     * 
     * @throws \InvalidArgumentException If required fields are missing or empty
     */
    private function validateConfig(array $config): void
    {
        $requiredFields = ['host', 'username', 'password', 'basePath'];
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (!isset($config[$field]) || $config[$field] === '') {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            throw new \InvalidArgumentException(
                sprintf('Missing required FTP configuration fields: %s', implode(', ', $missingFields))
            );
        }

        // Validate port if provided
        if (isset($config['port'])) {
            $port = (int) $config['port'];
            if ($port < 1 || $port > 65535) {
                throw new \InvalidArgumentException(
                    sprintf('Invalid FTP port: %d. Port must be between 1 and 65535', $port)
                );
            }
        }

        // Validate timeout if provided
        if (isset($config['timeout'])) {
            $timeout = (int) $config['timeout'];
            if ($timeout < 1) {
                throw new \InvalidArgumentException(
                    sprintf('Invalid FTP timeout: %d. Timeout must be positive', $timeout)
                );
            }
        }
    }
}
