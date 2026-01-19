<?php

declare(strict_types=1);

namespace App\Storage\Factory;

use App\Entity\Storage;
use App\Storage\StorageAdapterInterface;

/**
 * Interface for storage adapter factories.
 * 
 * Each storage type (FTP, SFTP, HTTP, Local) should have its own factory
 * implementing this interface.
 */
interface StorageAdapterFactoryInterface
{
    /**
     * Check if this factory supports the given storage type.
     */
    public function supports(Storage $storage): bool;

    /**
     * Create a storage adapter from the Storage entity configuration.
     * 
     * @throws \InvalidArgumentException If the storage type is not supported or config is invalid
     */
    public function create(Storage $storage): StorageAdapterInterface;
}
