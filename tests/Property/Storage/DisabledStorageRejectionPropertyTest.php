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
 * Property-based tests for disabled storage rejection.
 * 
 * **Feature: remote-storage, Property 3: Disabled storage rejects uploads**
 * **Validates: Requirements 1.8**
 * 
 * Property: For any upload attempt to a disabled storage, 
 * the system SHALL throw an exception or return failure.
 */
class DisabledStorageRejectionPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Property 3: Upload to disabled storage throws RuntimeException.
     * 
     * For any upload attempt to a disabled storage, the system SHALL throw
     * a RuntimeException indicating the storage is disabled.
     */
    public function testUploadToDisabledStorageThrowsException(): void
    {
        $this->forAll(
            Generator\suchThat(fn($s) => strlen(trim($s)) > 0, Generator\string()),
            Generator\suchThat(fn($s) => strlen(trim($s)) > 0, Generator\string())
        )->then(function (string $localPath, string $remotePath): void {
            // Создаём disabled storage
            $disabledStorage = $this->createDisabledStorage();
            
            $repository = $this->createMock(StorageRepository::class);
            $repository->method('findDefault')->willReturn(null);
            
            $storageManager = new StorageManager(
                $repository,
                new NullLogger(),
                []
            );
            
            // Проверяем, что выбрасывается исключение
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessageMatches('/disabled/i');
            
            $storageManager->uploadFile($localPath, $remotePath, $disabledStorage);
        });
    }

    /**
     * Property 3: Disabled default storage prevents uploads.
     * 
     * For any upload when default storage is disabled, the system SHALL
     * throw an exception.
     */
    public function testDisabledDefaultStorageRejectsUpload(): void
    {
        $this->forAll(
            Generator\suchThat(fn($s) => strlen(trim($s)) > 0, Generator\string()),
            Generator\suchThat(fn($s) => strlen(trim($s)) > 0, Generator\string())
        )->then(function (string $localPath, string $remotePath): void {
            // Создаём disabled default storage
            $disabledDefaultStorage = $this->createDisabledStorage();
            $disabledDefaultStorage->setIsDefault(true);
            
            $repository = $this->createMock(StorageRepository::class);
            $repository->method('findDefault')->willReturn($disabledDefaultStorage);
            
            $storageManager = new StorageManager(
                $repository,
                new NullLogger(),
                []
            );
            
            // Проверяем, что выбрасывается исключение
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessageMatches('/disabled/i');
            
            // Вызываем без указания storage - должен использоваться disabled default
            $storageManager->uploadFile($localPath, $remotePath);
        });
    }

    /**
     * Property 3: Enabled storage accepts uploads (контрольный тест).
     * 
     * For any upload to an enabled storage, the system SHALL NOT throw
     * a disabled storage exception.
     */
    public function testEnabledStorageAcceptsUpload(): void
    {
        $this->forAll(
            Generator\suchThat(fn($s) => strlen(trim($s)) > 0, Generator\string()),
            Generator\suchThat(fn($s) => strlen(trim($s)) > 0, Generator\string())
        )->then(function (string $localPath, string $remotePath): void {
            // Создаём enabled storage
            $enabledStorage = $this->createEnabledStorage();
            
            $repository = $this->createMock(StorageRepository::class);
            $repository->method('findDefault')->willReturn(null);
            
            // Создаём mock адаптера
            $mockAdapter = $this->createMockAdapter($remotePath);
            
            $factory = $this->createMock(StorageAdapterFactoryInterface::class);
            $factory->method('supports')->willReturn(true);
            $factory->method('create')->willReturn($mockAdapter);
            
            $storageManager = new StorageManager(
                $repository,
                new NullLogger(),
                [$factory]
            );
            
            // Не должно выбрасываться исключение о disabled storage
            $result = $storageManager->uploadFile($localPath, $remotePath, $enabledStorage);
            
            // Проверяем, что upload прошёл (не был отклонён из-за disabled)
            $this->assertTrue(
                $result->success,
                'Enabled storage should accept uploads'
            );
        });
    }

    /**
     * Property 3: Storage enabled state is correctly checked.
     * 
     * For any storage, isEnabled() SHALL return the correct state.
     */
    public function testStorageEnabledStateIsCorrect(): void
    {
        $this->forAll(
            Generator\bool()
        )->then(function (bool $isEnabled): void {
            $storage = new Storage();
            $storage->setName('Test Storage');
            $storage->setType(Storage::TYPE_LOCAL);
            $storage->setIsEnabled($isEnabled);
            $storage->setConfig([]);
            
            $this->assertSame(
                $isEnabled,
                $storage->isEnabled(),
                'Storage isEnabled() should return the set value'
            );
        });
    }

    /**
     * Создаёт disabled storage для тестов.
     */
    private function createDisabledStorage(): Storage
    {
        $storage = new Storage();
        $storage->setName('Disabled Storage');
        $storage->setType(Storage::TYPE_LOCAL);
        $storage->setIsDefault(false);
        $storage->setIsEnabled(false);
        $storage->setConfig([]);
        
        return $storage;
    }

    /**
     * Создаёт enabled storage для тестов.
     */
    private function createEnabledStorage(): Storage
    {
        $storage = new Storage();
        $storage->setName('Enabled Storage');
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
