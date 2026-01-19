<?php

declare(strict_types=1);

namespace App\Tests\Property\Storage;

use App\Entity\Storage;
use App\Repository\StorageRepository;
use App\Service\StorageManager;
use App\Storage\DTO\UploadResult;
use App\Storage\Factory\StorageAdapterFactoryInterface;
use App\Storage\StorageAdapterInterface;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Property-based tests for default storage usage.
 * 
 * **Feature: remote-storage, Property 2: Default storage is used for new uploads**
 * **Validates: Requirements 1.7**
 * 
 * Property: For any video upload when a default storage is configured, 
 * the resulting upload SHALL use that default storage.
 */
class DefaultStorageUsagePropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Property 2: When no storage is specified, default storage is used.
     * 
     * For any upload without explicit storage, the default storage SHALL be used.
     */
    public function testUploadWithoutStorageUsesDefault(): void
    {
        $this->forAll(
            Generator\suchThat(fn($s) => strlen(trim($s)) > 0, Generator\string()),
            Generator\suchThat(fn($s) => strlen(trim($s)) > 0, Generator\string())
        )->then(function (string $localPath, string $remotePath): void {
            // Создаём default storage
            $defaultStorage = $this->createDefaultStorage();
            
            // Создаём mock репозитория, который возвращает default storage
            $repository = $this->createMock(StorageRepository::class);
            $repository->method('findDefault')->willReturn($defaultStorage);
            
            // Создаём mock адаптера, который отслеживает вызовы
            $usedStorage = null;
            $mockAdapter = $this->createMockAdapter($remotePath);
            
            // Создаём mock фабрики
            $factory = $this->createMock(StorageAdapterFactoryInterface::class);
            $factory->method('supports')->willReturnCallback(
                function (Storage $storage) use ($defaultStorage, &$usedStorage): bool {
                    $usedStorage = $storage;
                    return $storage === $defaultStorage;
                }
            );
            $factory->method('create')->willReturn($mockAdapter);
            
            $storageManager = new StorageManager(
                $repository,
                new NullLogger(),
                [$factory]
            );
            
            // Вызываем uploadFile без указания storage
            $result = $storageManager->uploadFile($localPath, $remotePath);
            
            // Проверяем, что использовался default storage
            $this->assertSame(
                $defaultStorage,
                $usedStorage,
                'Default storage should be used when no storage is specified'
            );
        });
    }

    /**
     * Property 2: When default storage is configured and enabled, upload succeeds.
     * 
     * For any valid upload parameters with enabled default storage, 
     * the upload SHALL succeed.
     */
    public function testUploadSucceedsWithEnabledDefaultStorage(): void
    {
        $this->forAll(
            Generator\suchThat(fn($s) => strlen(trim($s)) > 0, Generator\string()),
            Generator\suchThat(fn($s) => strlen(trim($s)) > 0, Generator\string())
        )->then(function (string $localPath, string $remotePath): void {
            $defaultStorage = $this->createDefaultStorage();
            
            $repository = $this->createMock(StorageRepository::class);
            $repository->method('findDefault')->willReturn($defaultStorage);
            
            $mockAdapter = $this->createMockAdapter($remotePath);
            
            $factory = $this->createMock(StorageAdapterFactoryInterface::class);
            $factory->method('supports')->willReturn(true);
            $factory->method('create')->willReturn($mockAdapter);
            
            $storageManager = new StorageManager(
                $repository,
                new NullLogger(),
                [$factory]
            );
            
            $result = $storageManager->uploadFile($localPath, $remotePath);
            
            $this->assertTrue(
                $result->success,
                'Upload should succeed with enabled default storage'
            );
        });
    }

    /**
     * Property 2: When no default storage is configured, upload fails gracefully.
     * 
     * For any upload without default storage, the result SHALL indicate failure.
     */
    public function testUploadFailsWithoutDefaultStorage(): void
    {
        $this->forAll(
            Generator\suchThat(fn($s) => strlen(trim($s)) > 0, Generator\string()),
            Generator\suchThat(fn($s) => strlen(trim($s)) > 0, Generator\string())
        )->then(function (string $localPath, string $remotePath): void {
            // Репозиторий возвращает null (нет default storage)
            $repository = $this->createMock(StorageRepository::class);
            $repository->method('findDefault')->willReturn(null);
            
            $storageManager = new StorageManager(
                $repository,
                new NullLogger(),
                []
            );
            
            $result = $storageManager->uploadFile($localPath, $remotePath);
            
            $this->assertFalse(
                $result->success,
                'Upload should fail when no default storage is configured'
            );
            $this->assertStringContainsString(
                'No default storage',
                $result->errorMessage ?? '',
                'Error message should indicate missing default storage'
            );
        });
    }

    /**
     * Property 2: Explicit storage overrides default storage.
     * 
     * For any upload with explicit storage parameter, that storage SHALL be used
     * instead of the default.
     */
    public function testExplicitStorageOverridesDefault(): void
    {
        $this->forAll(
            Generator\suchThat(fn($s) => strlen(trim($s)) > 0, Generator\string()),
            Generator\suchThat(fn($s) => strlen(trim($s)) > 0, Generator\string())
        )->then(function (string $localPath, string $remotePath): void {
            $defaultStorage = $this->createDefaultStorage();
            $explicitStorage = $this->createExplicitStorage();
            
            $repository = $this->createMock(StorageRepository::class);
            $repository->method('findDefault')->willReturn($defaultStorage);
            
            $usedStorage = null;
            $mockAdapter = $this->createMockAdapter($remotePath);
            
            $factory = $this->createMock(StorageAdapterFactoryInterface::class);
            $factory->method('supports')->willReturnCallback(
                function (Storage $storage) use (&$usedStorage): bool {
                    $usedStorage = $storage;
                    return true;
                }
            );
            $factory->method('create')->willReturn($mockAdapter);
            
            $storageManager = new StorageManager(
                $repository,
                new NullLogger(),
                [$factory]
            );
            
            // Вызываем uploadFile с явным указанием storage
            $result = $storageManager->uploadFile($localPath, $remotePath, $explicitStorage);
            
            // Проверяем, что использовался explicit storage, а не default
            $this->assertSame(
                $explicitStorage,
                $usedStorage,
                'Explicit storage should override default storage'
            );
            $this->assertNotSame(
                $defaultStorage,
                $usedStorage,
                'Default storage should not be used when explicit storage is provided'
            );
        });
    }

    /**
     * Создаёт default storage для тестов.
     */
    private function createDefaultStorage(): Storage
    {
        $storage = new Storage();
        $storage->setName('Default Storage');
        $storage->setType(Storage::TYPE_LOCAL);
        $storage->setIsDefault(true);
        $storage->setIsEnabled(true);
        $storage->setConfig([]);
        
        return $storage;
    }

    /**
     * Создаёт explicit storage для тестов.
     */
    private function createExplicitStorage(): Storage
    {
        $storage = new Storage();
        $storage->setName('Explicit Storage');
        $storage->setType(Storage::TYPE_LOCAL);
        $storage->setIsDefault(false);
        $storage->setIsEnabled(true);
        $storage->setConfig([]);
        
        return $storage;
    }

    /**
     * Создаёт mock адаптера для тестов.
     */
    private function createMockAdapter(string $remotePath): StorageAdapterInterface
    {
        $adapter = $this->createMock(StorageAdapterInterface::class);
        $adapter->method('upload')->willReturn(
            UploadResult::success($remotePath)
        );
        $adapter->method('createDirectory')->willReturn(true);
        
        return $adapter;
    }
}
