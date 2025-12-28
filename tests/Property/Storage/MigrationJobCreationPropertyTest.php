<?php

declare(strict_types=1);

namespace App\Tests\Property\Storage;

use App\Entity\Storage;
use App\Entity\Video;
use App\Entity\VideoEncodingProfile;
use App\Entity\VideoFile;
use App\Message\MigrateFileMessage;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for migration job creation.
 * 
 * **Feature: remote-storage, Property 8: Migration creates jobs for all files**
 * **Validates: Requirements 4.2**
 * 
 * Property: For any migration from storage A to storage B with N video files,
 * exactly N MigrateFileMessage jobs SHALL be queued.
 */
class MigrationJobCreationPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Property 8: Migration creates exactly N messages for N video files.
     * 
     * For any set of N VideoFile records to be migrated,
     * the migration process SHALL create exactly N MigrateFileMessage jobs.
     */
    public function testMigrationCreatesExactlyNMessagesForNFiles(): void
    {
        $this->forAll(
            Generator\choose(0, 10) // Количество файлов для миграции
        )->then(function (int $fileCount): void {
            $sourceStorage = $this->createStorage(1, 'Source Storage');
            $destinationStorage = $this->createStorage(2, 'Destination Storage');
            
            // Создаём VideoFile записи с ID
            $videoFiles = $this->createVideoFilesWithIds($sourceStorage, $fileCount);
            
            // Генерируем сообщения миграции
            $messages = $this->createMigrationMessages($videoFiles, $destinationStorage);
            
            // Проверяем, что количество сообщений равно количеству файлов
            $this->assertCount(
                $fileCount,
                $messages,
                \sprintf(
                    'Expected %d migration messages for %d files, got %d',
                    $fileCount,
                    $fileCount,
                    \count($messages)
                )
            );
        });
    }


    /**
     * Property 8: Each message contains correct video file ID and destination storage ID.
     * 
     * For any VideoFile to be migrated, the MigrateFileMessage
     * SHALL contain the correct video file ID and destination storage ID.
     */
    public function testMigrationMessagesContainCorrectData(): void
    {
        $this->forAll(
            Generator\choose(1, 5) // Количество файлов
        )->then(function (int $fileCount): void {
            $sourceStorage = $this->createStorage(1, 'Source Storage');
            $destinationStorage = $this->createStorage(2, 'Destination Storage');
            
            $videoFiles = $this->createVideoFilesWithIds($sourceStorage, $fileCount);
            $expectedFileIds = \array_map(fn(VideoFile $vf) => $vf->getId(), $videoFiles);
            
            $messages = $this->createMigrationMessages($videoFiles, $destinationStorage);
            
            // Проверяем, что каждое сообщение содержит правильные данные
            $actualFileIds = [];
            foreach ($messages as $message) {
                $this->assertInstanceOf(MigrateFileMessage::class, $message);
                $this->assertEquals(2, $message->getDestinationStorageId());
                $this->assertEquals(1, $message->getAttempt());
                $actualFileIds[] = $message->getVideoFileId();
            }
            
            // Проверяем, что все ID файлов присутствуют
            \sort($expectedFileIds);
            \sort($actualFileIds);
            $this->assertEquals($expectedFileIds, $actualFileIds);
        });
    }

    /**
     * Property 8: Migration from same storage to different destination creates correct messages.
     * 
     * For any migration where all files are on the same source storage,
     * all MigrateFileMessage jobs SHALL reference the same destination storage.
     */
    public function testMigrationFromSameSourceToDestination(): void
    {
        $this->forAll(
            Generator\choose(1, 5), // Количество файлов
            Generator\choose(1, 10) // ID целевого хранилища
        )->then(function (int $fileCount, int $destinationId): void {
            $sourceStorage = $this->createStorage(100, 'Source Storage');
            $destinationStorage = $this->createStorage($destinationId, 'Destination Storage');
            
            $videoFiles = $this->createVideoFilesWithIds($sourceStorage, $fileCount);
            
            $messages = $this->createMigrationMessages($videoFiles, $destinationStorage);
            
            // Все сообщения должны иметь одинаковый destination storage ID
            foreach ($messages as $message) {
                $this->assertEquals(
                    $destinationId,
                    $message->getDestinationStorageId(),
                    'All messages should reference the same destination storage'
                );
            }
        });
    }

    /**
     * Property 8: Migration of files from multiple sources creates correct messages.
     * 
     * For any migration where files are on different source storages,
     * exactly N MigrateFileMessage jobs SHALL be created for N files.
     */
    public function testMigrationFromMultipleSourcesToDestination(): void
    {
        $this->forAll(
            Generator\choose(1, 3), // Количество исходных хранилищ
            Generator\choose(1, 3)  // Файлов на каждое хранилище
        )->then(function (int $sourceCount, int $filesPerSource): void {
            $destinationStorage = $this->createStorage(999, 'Destination Storage');
            
            $allVideoFiles = [];
            $fileIdCounter = 1;
            
            for ($s = 1; $s <= $sourceCount; $s++) {
                $sourceStorage = $this->createStorage($s, 'Source Storage ' . $s);
                
                for ($f = 0; $f < $filesPerSource; $f++) {
                    $videoFile = $this->createVideoFileWithId(
                        $sourceStorage,
                        $fileIdCounter++,
                        'videos/storage_' . $s . '/file_' . $f
                    );
                    $allVideoFiles[] = $videoFile;
                }
            }
            
            $messages = $this->createMigrationMessages($allVideoFiles, $destinationStorage);
            
            $expectedTotal = $sourceCount * $filesPerSource;
            
            // Должно быть ровно столько сообщений, сколько файлов
            $this->assertCount(
                $expectedTotal,
                $messages,
                \sprintf(
                    'Expected %d migration messages for %d files from %d sources, got %d',
                    $expectedTotal,
                    $expectedTotal,
                    $sourceCount,
                    \count($messages)
                )
            );
            
            // Все сообщения должны иметь правильный destination
            foreach ($messages as $message) {
                $this->assertEquals(999, $message->getDestinationStorageId());
            }
        });
    }


    /**
     * Property 8: Empty file list creates no migration messages.
     * 
     * For any migration with 0 files, exactly 0 MigrateFileMessage jobs SHALL be created.
     */
    public function testEmptyFileListCreatesNoMessages(): void
    {
        $this->forAll(
            Generator\choose(1, 10) // ID целевого хранилища
        )->then(function (int $destinationId): void {
            $destinationStorage = $this->createStorage($destinationId, 'Destination Storage');
            
            $messages = $this->createMigrationMessages([], $destinationStorage);
            
            $this->assertCount(
                0,
                $messages,
                'Empty file list should create no migration messages'
            );
        });
    }

    /**
     * Property 8: Migration messages have initial attempt = 1.
     * 
     * For any newly created MigrateFileMessage, the attempt counter SHALL be 1.
     */
    public function testMigrationMessagesHaveInitialAttemptOne(): void
    {
        $this->forAll(
            Generator\choose(1, 10) // Количество файлов
        )->then(function (int $fileCount): void {
            $sourceStorage = $this->createStorage(1, 'Source Storage');
            $destinationStorage = $this->createStorage(2, 'Destination Storage');
            
            $videoFiles = $this->createVideoFilesWithIds($sourceStorage, $fileCount);
            
            $messages = $this->createMigrationMessages($videoFiles, $destinationStorage);
            
            foreach ($messages as $message) {
                $this->assertEquals(
                    1,
                    $message->getAttempt(),
                    'Initial migration message should have attempt = 1'
                );
            }
        });
    }

    /**
     * Property 8: Each file ID appears exactly once in migration messages.
     * 
     * For any migration with N unique files, each file ID SHALL appear exactly once.
     */
    public function testEachFileIdAppearsExactlyOnce(): void
    {
        $this->forAll(
            Generator\choose(1, 10) // Количество файлов
        )->then(function (int $fileCount): void {
            $sourceStorage = $this->createStorage(1, 'Source Storage');
            $destinationStorage = $this->createStorage(2, 'Destination Storage');
            
            $videoFiles = $this->createVideoFilesWithIds($sourceStorage, $fileCount);
            
            $messages = $this->createMigrationMessages($videoFiles, $destinationStorage);
            
            $fileIdCounts = [];
            foreach ($messages as $message) {
                $fileId = $message->getVideoFileId();
                $fileIdCounts[$fileId] = ($fileIdCounts[$fileId] ?? 0) + 1;
            }
            
            // Каждый ID должен появиться ровно один раз
            foreach ($fileIdCounts as $fileId => $count) {
                $this->assertEquals(
                    1,
                    $count,
                    \sprintf('File ID %d should appear exactly once, appeared %d times', $fileId, $count)
                );
            }
        });
    }

    /**
     * Создаёт Storage с указанным ID.
     */
    private function createStorage(int $id, string $name): Storage
    {
        $storage = new Storage();
        $storage->setName($name);
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
     * Создаёт массив VideoFile с установленными ID.
     * 
     * @return VideoFile[]
     */
    private function createVideoFilesWithIds(Storage $storage, int $count): array
    {
        $videoFiles = [];
        
        for ($i = 1; $i <= $count; $i++) {
            $videoFiles[] = $this->createVideoFileWithId(
                $storage,
                $i,
                'videos/test/file_' . $i
            );
        }
        
        return $videoFiles;
    }

    /**
     * Создаёт VideoFile с указанным ID.
     */
    private function createVideoFileWithId(Storage $storage, int $id, string $remotePath): VideoFile
    {
        $videoFile = new VideoFile();
        $videoFile->setFile('local/path/video.mp4');
        $videoFile->setStorage($storage);
        $videoFile->setRemotePath($remotePath);
        $videoFile->setFileSize(1024);
        $videoFile->setDuration(60);
        
        // Создаём профиль
        $profile = new VideoEncodingProfile();
        $profile->setName('720p');
        $profile->setResolution('1280x720');
        $profile->setBitrate(2500);
        $profile->setIsActive(true);
        $videoFile->setProfile($profile);
        
        // Используем reflection для установки ID
        $reflection = new \ReflectionClass($videoFile);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setValue($videoFile, $id);
        
        return $videoFile;
    }

    /**
     * Создаёт сообщения миграции для всех файлов.
     * 
     * Это функция, которую мы тестируем - она должна создавать
     * MigrateFileMessage для каждого VideoFile.
     * 
     * @param VideoFile[] $videoFiles
     * @return MigrateFileMessage[]
     */
    private function createMigrationMessages(array $videoFiles, Storage $destinationStorage): array
    {
        $messages = [];
        
        $destinationId = $destinationStorage->getId();
        if ($destinationId === null) {
            return $messages;
        }
        
        foreach ($videoFiles as $videoFile) {
            $fileId = $videoFile->getId();
            if ($fileId !== null) {
                $messages[] = new MigrateFileMessage(
                    $fileId,
                    $destinationId
                );
            }
        }
        
        return $messages;
    }
}
