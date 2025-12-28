<?php

declare(strict_types=1);

namespace App\Storage\Factory;

use App\Entity\Storage;
use App\Storage\Adapter\SftpStorageAdapter;
use App\Storage\StorageAdapterInterface;

/**
 * Factory for creating SftpStorageAdapter instances from Storage entity configuration.
 * 
 * Validates: Requirements 1.3
 */
class SftpStorageAdapterFactory implements StorageAdapterFactoryInterface
{
    /**
     * Check if this factory supports the given storage type.
     */
    public function supports(Storage $storage): bool
    {
        return $storage->getType() === Storage::TYPE_SFTP;
    }

    /**
     * Create SftpStorageAdapter from Storage entity configuration.
     * 
     * Expected config structure:
     * {
     *   "host": "sftp.example.com",
     *   "port": 22,
     *   "username": "user",
     *   "authType": "password|key",
     *   "password": "pass",
     *   "privateKey": "-----BEGIN RSA PRIVATE KEY-----...",
     *   "privateKeyPassphrase": "passphrase",
     *   "basePath": "/uploads",
     *   "timeout": 30
     * }
     * 
     * @throws \InvalidArgumentException If required config fields are missing
     */
    public function create(Storage $storage): StorageAdapterInterface
    {
        if (!$this->supports($storage)) {
            throw new \InvalidArgumentException(
                sprintf('SftpStorageAdapterFactory does not support storage type: %s', $storage->getType())
            );
        }

        $config = $storage->getConfig();
        
        $this->validateConfig($config);

        return new SftpStorageAdapter(
            host: $config['host'],
            port: (int) ($config['port'] ?? 22),
            username: $config['username'],
            password: $config['password'] ?? null,
            privateKey: $config['privateKey'] ?? null,
            privateKeyPassphrase: $config['privateKeyPassphrase'] ?? null,
            basePath: $config['basePath'],
            timeout: (int) ($config['timeout'] ?? 30),
        );
    }

    /**
     * Validate that all required configuration fields are present.
     * 
     * Requirement 1.3: WHEN an administrator creates a new SFTP storage THEN the System 
     * SHALL require host, port, username, authentication method (password or key), and base path fields
     * 
     * @throws \InvalidArgumentException If required fields are missing or empty
     */
    private function validateConfig(array $config): void
    {
        $requiredFields = ['host', 'username', 'basePath'];
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (!isset($config[$field]) || $config[$field] === '') {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            throw new \InvalidArgumentException(
                sprintf('Missing required SFTP configuration fields: %s', implode(', ', $missingFields))
            );
        }

        // Validate authentication method - must have either password or privateKey
        $hasPassword = isset($config['password']) && $config['password'] !== '';
        $hasPrivateKey = isset($config['privateKey']) && $config['privateKey'] !== '';

        if (!$hasPassword && !$hasPrivateKey) {
            throw new \InvalidArgumentException(
                'SFTP authentication required: provide either password or privateKey'
            );
        }

        // Validate port if provided
        if (isset($config['port'])) {
            $port = (int) $config['port'];
            if ($port < 1 || $port > 65535) {
                throw new \InvalidArgumentException(
                    sprintf('Invalid SFTP port: %d. Port must be between 1 and 65535', $port)
                );
            }
        }

        // Validate timeout if provided
        if (isset($config['timeout'])) {
            $timeout = (int) $config['timeout'];
            if ($timeout < 1) {
                throw new \InvalidArgumentException(
                    sprintf('Invalid SFTP timeout: %d. Timeout must be positive', $timeout)
                );
            }
        }
    }
}
