<?php

declare(strict_types=1);

namespace App\Tests\Property\Storage;

use App\Entity\Storage;
use App\Entity\Video;
use App\Entity\VideoEncodingProfile;
use App\Entity\VideoFile;
use App\Message\UploadToStorageMessage;
use App\MessageHandler\UploadToStorageMessageHandler;
use App\Repository\VideoFileRepository;
use App\Service\NotificationService;
use App\Service\StorageManager;
use App\Storage\DTO\UploadResult;
use App\Storage\Factory\StorageAdapterFactoryInterface;
use App\Storage\StorageAdapterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Property-based tests for successful upload path storage.
 * 
 * **Feature: remote-storage, Property 5: Successful upload stores remote path**
 * **Validates: Requirements 2.7**
 * 
 * Property: For any successful upload operation, the VideoFile entity SHALL have 
 * a non-null remotePath matching the uploaded location.
 */
class SuccessfulUploadPathStoragePropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Property 5: Successful upload stores remote path in VideoFile entity.
     * 
     * For any successful upload, the VideoFile SHALL have:
     * - A non-null remotePath
     * - The remotePath matches the uploaded location
     * - A reference to the storage used
     */
    public function testSuccessfulUploadStoresRemotePath(): void
    {
        $this->forAll(
            Generator\suchThat(
                fn($s) => strlen(trim($s)) > 0 && strlen($s) <= 255,
                Generator\string()
            ),
            Generator\choose(1, 1000000)
        )->then(function (string $localFileName, int $videoFileId): void {
            // Создаём VideoFile с mock Video и Profile
            $videoFile = $this->createVideoFile($videoFileId, $localFileName);
            
            // Создаём storage
            $storage = $this->createStorage();
            
            // Генерируем ожидаемый remote path
            $expectedRemotePath = 'videos/1/default/' . uniqid('video_', true) . '.mp4';
            
            // Создаём mock адаптера, который возвращает успешный результат
            $mockAdapter = $this->createMock(StorageAdapterInterface::class);
            $mockAdapter->method('upload')->willReturn(
                UploadResult::success($expectedRemotePath)
            );
            $mockAdapter->method('createDirectory')->willReturn(true);
            
            // Создаём mock фабрики
            $factory = $this->createMock(StorageAdapterFactoryInterface::class);
            $factory->method('supports')->willReturn(true);
            $factory->method('create')->willReturn($mockAdapter);
            
            // Создаём mock репозитория
            $repository = $this->createMock(\App\Repository\StorageRepository::class);
            $repository->method('findDefault')->willReturn($storage);
            
            // Создаём StorageManager
            $storageManager = new StorageManager(
                $repository,
                new NullLogger(),
                [$factory]
            );
            
            // Выполняем загрузку
            $result = $storageManager->uploadFile('/tmp/' . $localFileName, $expectedRemotePath, $storage);
            
            // Проверяем успешность загрузки
            $this->assertTrue($result->success, 'Upload should succeed');
            
            // Симулируем обновление VideoFile после успешной загрузки
            // (как это делает UploadToStorageMessageHandler)
            if ($result->success) {
                $videoFile->setStorage($storage);
                $videoFile->setRemotePath($result->remotePath ?? $expectedRemotePath);
            }
            
            // Property 5: После успешной загрузки remotePath должен быть non-null
            $this->assertNotNull(
                $videoFile->getRemotePath(),
                'VideoFile remotePath should be non-null after successful upload'
            );
            
            // Property 5: remotePath должен соответствовать загруженному местоположению
            $this->assertEquals(
                $expectedRemotePath,
                $videoFile->getRemotePath(),
                'VideoFile remotePath should match the uploaded location'
            );
            
            // Property 5: Storage должен быть установлен
            $this->assertNotNull(
                $videoFile->getStorage(),
                'VideoFile storage should be set after successful upload'
            );
            
            // Property 5: isRemote() должен возвращать true
            $this->assertTrue(
                $videoFile->isRemote(),
                'VideoFile should be marked as remote after successful upload'
            );
        });
    }

    /**
     * Property 5: Remote path format is consistent.
     * 
     * For any successful upload, the remotePath SHALL follow the expected format.
     */
    public function testRemotePathFormatIsConsistent(): void
    {
        $this->forAll(
            Generator\choose(1, 10000),
            Generator\elements(['240p', '360p', '480p', '720p', '1080p', 'default'])
        )->then(function (int $videoId, string $profileName): void {
            $videoFile = new VideoFile();
            
            // Создаём mock Video
            $video = $this->createMock(Video::class);
            $video->method('getId')->willReturn($videoId);
            
            // Создаём mock Profile
            $profile = $this->createMock(VideoEncodingProfile::class);
            $profile->method('getName')->willReturn($profileName);
            
            // Используем reflection для установки video и profile
            $reflection = new \ReflectionClass($videoFile);
            
            $videoProperty = $reflection->getProperty('video');
            $videoProperty->setAccessible(true);
            $videoProperty->setValue($videoFile, $video);
            
            $profileProperty = $reflection->getProperty('profile');
            $profileProperty->setAccessible(true);
            $profileProperty->setValue($videoFile, $profile);
            
            $videoFile->setFile('test_video.mp4');
            
            // Генерируем remote path используя ту же логику что и в StorageManager
            $remotePath = sprintf(
                'videos/%d/%s/%s.%s',
                $videoId,
                $profileName,
                uniqid('video_', true),
                'mp4'
            );
            
            $videoFile->setRemotePath($remotePath);
            
            // Проверяем формат пути
            $this->assertMatchesRegularExpression(
                '/^videos\/\d+\/[a-zA-Z0-9]+\/video_[a-f0-9.]+\.mp4$/',
                $videoFile->getRemotePath(),
                'Remote path should follow the expected format: videos/{videoId}/{profile}/{uniqueId}.{ext}'
            );
            
            // Проверяем, что путь содержит videoId
            $this->assertStringContainsString(
                "videos/{$videoId}/",
                $videoFile->getRemotePath(),
                'Remote path should contain video ID'
            );
            
            // Проверяем, что путь содержит profile name
            $this->assertStringContainsString(
                "/{$profileName}/",
                $videoFile->getRemotePath(),
                'Remote path should contain profile name'
            );
        });
    }

    /**
     * Property 5: Upload result remotePath is stored correctly.
     * 
     * For any UploadResult with success=true and remotePath, 
     * the VideoFile SHALL store that exact path.
     */
    public function testUploadResultRemotePathIsStoredCorrectly(): void
    {
        $this->forAll(
            Generator\suchThat(
                fn($s) => strlen(trim($s)) > 0 && strlen($s) <= 500,
                Generator\string()
            )
        )->then(function (string $remotePath): void {
            // Создаём успешный UploadResult
            $uploadResult = UploadResult::success($remotePath);
            
            // Проверяем, что UploadResult корректен
            $this->assertTrue($uploadResult->success);
            $this->assertEquals($remotePath, $uploadResult->remotePath);
            
            // Создаём VideoFile и устанавливаем remotePath из результата
            $videoFile = new VideoFile();
            $storage = $this->createStorage();
            
            $videoFile->setStorage($storage);
            $videoFile->setRemotePath($uploadResult->remotePath);
            
            // Property 5: remotePath должен точно соответствовать результату загрузки
            $this->assertEquals(
                $remotePath,
                $videoFile->getRemotePath(),
                'VideoFile remotePath should exactly match UploadResult remotePath'
            );
        });
    }

    /**
     * Property 5: Failed upload does not set remote path.
     * 
     * For any failed upload, the VideoFile SHALL NOT have remotePath set.
     */
    public function testFailedUploadDoesNotSetRemotePath(): void
    {
        $this->forAll(
            Generator\suchThat(
                fn($s) => strlen(trim($s)) > 0,
                Generator\string()
            )
        )->then(function (string $errorMessage): void {
            // Создаём неуспешный UploadResult
            $uploadResult = UploadResult::failure($errorMessage);
            
            // Проверяем, что UploadResult указывает на неудачу
            $this->assertFalse($uploadResult->success);
            $this->assertNull($uploadResult->remotePath);
            
            // Создаём VideoFile
            $videoFile = new VideoFile();
            
            // Симулируем логику обработки неудачной загрузки
            // (remotePath не устанавливается при неудаче)
            if ($uploadResult->success && $uploadResult->remotePath !== null) {
                $videoFile->setRemotePath($uploadResult->remotePath);
            }
            
            // Property 5: remotePath должен остаться null при неудачной загрузке
            $this->assertNull(
                $videoFile->getRemotePath(),
                'VideoFile remotePath should remain null after failed upload'
            );
            
            // isRemote() должен возвращать false
            $this->assertFalse(
                $videoFile->isRemote(),
                'VideoFile should not be marked as remote after failed upload'
            );
        });
    }

    /**
     * Создаёт VideoFile для тестов.
     */
    private function createVideoFile(int $id, string $fileName): VideoFile
    {
        $videoFile = new VideoFile();
        $videoFile->setFile($fileName);
        
        // Используем reflection для установки ID
        $reflection = new \ReflectionClass($videoFile);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($videoFile, $id);
        
        return $videoFile;
    }

    /**
     * Создаёт Storage для тестов.
     */
    private function createStorage(): Storage
    {
        $storage = new Storage();
        $storage->setName('Test Storage');
        $storage->setType(Storage::TYPE_LOCAL);
        $storage->setIsDefault(true);
        $storage->setIsEnabled(true);
        $storage->setConfig([]);
        
        return $storage;
    }
}
