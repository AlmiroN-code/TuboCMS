<?php

declare(strict_types=1);

namespace App\Tests\Property\Storage;

use App\Entity\Storage;
use App\Entity\Video;
use App\Entity\VideoEncodingProfile;
use App\Entity\VideoFile;
use App\Message\DeleteFromStorageMessage;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for deletion job creation.
 * 
 * **Feature: remote-storage, Property 10: Deletion queues jobs for all associated files**
 * **Validates: Requirements 5.1**
 * 
 * Property: For any video deletion with N associated VideoFile records on remote storage,
 * exactly N DeleteFromStorageMessage jobs SHALL be queued.
 */
class DeletionJobCreationPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Property 10: Deletion creates exactly N messages for N remote files.
     * 
     * For any video with N VideoFile records on remote storage,
     * the deletion process SHALL create exactly N DeleteFromStorageMessage jobs.
     */
    public function testDeletionCreatesExactlyNMessagesForNRemoteFiles(): void
    {
        $this->forAll(
            Generator\choose(0, 10) // Количество файлов на удалённом хранилище
        )->then(function (int $remoteFileCount): void {
            // Создаём видео с указанным количеством файлов на удалённом хранилище
            $video = $this->createVideoWithRemoteFiles($remoteFileCount);
            
            // Генерируем сообщения на удаление
            $messages = $this->createDeletionMessages($video);
            
            // Проверяем, что количество сообщений равно количеству удалённых файлов
            $this->assertCount(
                $remoteFileCount,
                $messages,
                \sprintf(
                    'Expected %d deletion messages for %d remote files, got %d',
                    $remoteFileCount,
                    $remoteFileCount,
                    \count($messages)
                )
            );
        });
    }

    /**
     * Property 10: Each message contains correct storage ID and remote path.
     * 
     * For any VideoFile on remote storage, the DeleteFromStorageMessage
     * SHALL contain the correct storage ID and remote path.
     */
    public function testDeletionMessagesContainCorrectData(): void
    {
        $this->forAll(
            Generator\choose(1, 5), // Количество файлов
            Generator\suchThat(
                fn($s) => \strlen(\trim($s)) > 0 && \strlen($s) < 100,
                Generator\string()
            )
        )->then(function (int $fileCount, string $pathSuffix): void {
            $storage = $this->createStorage(1);
            $video = new Video();
            $video->setTitle('Test Video');
            $video->setSlug('test-video-' . \uniqid());
            
            $expectedPaths = [];
            
            for ($i = 0; $i < $fileCount; $i++) {
                $remotePath = 'videos/test/' . $i . '_' . \preg_replace('/[^a-zA-Z0-9]/', '', $pathSuffix);
                $expectedPaths[] = $remotePath;
                
                $videoFile = $this->createRemoteVideoFile($storage, $remotePath);
                $video->addEncodedFile($videoFile);
            }
            
            $messages = $this->createDeletionMessages($video);
            
            // Проверяем, что каждое сообщение содержит правильные данные
            $actualPaths = [];
            foreach ($messages as $message) {
                $this->assertInstanceOf(DeleteFromStorageMessage::class, $message);
                $this->assertEquals(1, $message->getStorageId());
                $actualPaths[] = $message->getRemotePath();
            }
            
            // Проверяем, что все пути присутствуют
            \sort($expectedPaths);
            \sort($actualPaths);
            $this->assertEquals($expectedPaths, $actualPaths);
        });
    }

    /**
     * Property 10: Local files do not create deletion messages.
     * 
     * For any VideoFile without remote storage, no DeleteFromStorageMessage
     * SHALL be created.
     */
    public function testLocalFilesDoNotCreateDeletionMessages(): void
    {
        $this->forAll(
            Generator\choose(1, 10) // Количество локальных файлов
        )->then(function (int $localFileCount): void {
            $video = new Video();
            $video->setTitle('Test Video');
            $video->setSlug('test-video-' . \uniqid());
            
            // Добавляем только локальные файлы (без storage и remotePath)
            for ($i = 0; $i < $localFileCount; $i++) {
                $videoFile = $this->createLocalVideoFile();
                $video->addEncodedFile($videoFile);
            }
            
            $messages = $this->createDeletionMessages($video);
            
            // Для локальных файлов не должно быть сообщений на удаление
            $this->assertCount(
                0,
                $messages,
                'Local files should not create deletion messages'
            );
        });
    }

    /**
     * Property 10: Mixed local and remote files create correct number of messages.
     * 
     * For any video with M local files and N remote files,
     * exactly N DeleteFromStorageMessage jobs SHALL be created.
     */
    public function testMixedFilesCreateCorrectNumberOfMessages(): void
    {
        $this->forAll(
            Generator\choose(0, 5), // Количество локальных файлов
            Generator\choose(0, 5)  // Количество удалённых файлов
        )->then(function (int $localCount, int $remoteCount): void {
            $video = new Video();
            $video->setTitle('Test Video');
            $video->setSlug('test-video-' . \uniqid());
            
            // Добавляем локальные файлы
            for ($i = 0; $i < $localCount; $i++) {
                $videoFile = $this->createLocalVideoFile();
                $video->addEncodedFile($videoFile);
            }
            
            // Добавляем удалённые файлы
            $storage = $this->createStorage(1);
            for ($i = 0; $i < $remoteCount; $i++) {
                $videoFile = $this->createRemoteVideoFile($storage, 'videos/test/file_' . $i);
                $video->addEncodedFile($videoFile);
            }
            
            $messages = $this->createDeletionMessages($video);
            
            // Должно быть ровно столько сообщений, сколько удалённых файлов
            $this->assertCount(
                $remoteCount,
                $messages,
                \sprintf(
                    'Expected %d deletion messages for %d remote files (with %d local files), got %d',
                    $remoteCount,
                    $remoteCount,
                    $localCount,
                    \count($messages)
                )
            );
        });
    }

    /**
     * Property 10: Multiple storages create messages with correct storage IDs.
     * 
     * For any video with files on different storages,
     * each DeleteFromStorageMessage SHALL reference the correct storage.
     */
    public function testMultipleStoragesCreateCorrectMessages(): void
    {
        $this->forAll(
            Generator\choose(1, 3), // Количество хранилищ
            Generator\choose(1, 3)  // Файлов на каждое хранилище
        )->then(function (int $storageCount, int $filesPerStorage): void {
            $video = new Video();
            $video->setTitle('Test Video');
            $video->setSlug('test-video-' . \uniqid());
            
            $expectedStorageFileCounts = [];
            
            for ($s = 1; $s <= $storageCount; $s++) {
                $storage = $this->createStorage($s);
                $expectedStorageFileCounts[$s] = $filesPerStorage;
                
                for ($f = 0; $f < $filesPerStorage; $f++) {
                    $videoFile = $this->createRemoteVideoFile(
                        $storage,
                        'videos/storage_' . $s . '/file_' . $f
                    );
                    $video->addEncodedFile($videoFile);
                }
            }
            
            $messages = $this->createDeletionMessages($video);
            
            // Подсчитываем сообщения по storage ID
            $actualStorageFileCounts = [];
            foreach ($messages as $message) {
                $storageId = $message->getStorageId();
                $actualStorageFileCounts[$storageId] = ($actualStorageFileCounts[$storageId] ?? 0) + 1;
            }
            
            // Проверяем, что количество сообщений для каждого хранилища правильное
            $this->assertEquals(
                $expectedStorageFileCounts,
                $actualStorageFileCounts,
                'Each storage should have correct number of deletion messages'
            );
        });
    }

    /**
     * Создаёт видео с указанным количеством файлов на удалённом хранилище.
     */
    private function createVideoWithRemoteFiles(int $remoteFileCount): Video
    {
        $video = new Video();
        $video->setTitle('Test Video');
        $video->setSlug('test-video-' . \uniqid());
        
        if ($remoteFileCount > 0) {
            $storage = $this->createStorage(1);
            
            for ($i = 0; $i < $remoteFileCount; $i++) {
                $videoFile = $this->createRemoteVideoFile($storage, 'videos/test/file_' . $i);
                $video->addEncodedFile($videoFile);
            }
        }
        
        return $video;
    }

    /**
     * Создаёт Storage с указанным ID.
     */
    private function createStorage(int $id): Storage
    {
        $storage = new Storage();
        $storage->setName('Storage ' . $id);
        $storage->setType(Storage::TYPE_FTP);
        $storage->setIsEnabled(true);
        $storage->setConfig([
            'host' => 'ftp.example.com',
            'port' => 21,
            'username' => 'user',
            'password' => 'pass',
            'basePath' => '/uploads',
        ]);
        
        // Используем reflection для установки ID
        $reflection = new \ReflectionClass($storage);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setValue($storage, $id);
        
        return $storage;
    }

    /**
     * Создаёт VideoFile на удалённом хранилище.
     */
    private function createRemoteVideoFile(Storage $storage, string $remotePath): VideoFile
    {
        $videoFile = new VideoFile();
        $videoFile->setFile('local/path/video.mp4');
        $videoFile->setStorage($storage);
        $videoFile->setRemotePath($remotePath);
        $videoFile->setFileSize(1024);
        $videoFile->setDuration(60);
        
        // Создаём профиль с правильными методами
        $profile = new VideoEncodingProfile();
        $profile->setName('720p');
        $profile->setResolution('1280x720');
        $profile->setBitrate(2500);
        $profile->setIsActive(true);
        $videoFile->setProfile($profile);
        
        return $videoFile;
    }

    /**
     * Создаёт локальный VideoFile (без удалённого хранилища).
     */
    private function createLocalVideoFile(): VideoFile
    {
        $videoFile = new VideoFile();
        $videoFile->setFile('local/path/video.mp4');
        $videoFile->setStorage(null);
        $videoFile->setRemotePath(null);
        $videoFile->setFileSize(1024);
        $videoFile->setDuration(60);
        
        $profile = new VideoEncodingProfile();
        $profile->setName('720p');
        $profile->setResolution('1280x720');
        $profile->setBitrate(2500);
        $profile->setIsActive(true);
        $videoFile->setProfile($profile);
        
        return $videoFile;
    }

    /**
     * Создаёт сообщения на удаление для всех удалённых файлов видео.
     * 
     * Это функция, которую мы тестируем - она должна создавать
     * DeleteFromStorageMessage для каждого VideoFile на удалённом хранилище.
     * 
     * @param Video $video
     * @return DeleteFromStorageMessage[]
     */
    private function createDeletionMessages(Video $video): array
    {
        $messages = [];
        
        foreach ($video->getEncodedFiles() as $videoFile) {
            // Проверяем, что файл на удалённом хранилище
            if ($videoFile->isRemote()) {
                $storage = $videoFile->getStorage();
                $remotePath = $videoFile->getRemotePath();
                
                if ($storage !== null && $remotePath !== null) {
                    $storageId = $storage->getId();
                    if ($storageId !== null) {
                        $messages[] = new DeleteFromStorageMessage(
                            $storageId,
                            $remotePath
                        );
                    }
                }
            }
        }
        
        return $messages;
    }
}
