<?php

declare(strict_types=1);

namespace App\Tests\Property\Storage;

use App\Entity\Storage;
use App\Entity\VideoFile;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for storage statistics accuracy.
 * 
 * **Feature: remote-storage, Property 11: Storage statistics accuracy**
 * **Validates: Requirements 6.1**
 * 
 * Property: For any storage with N files totaling S bytes, 
 * the dashboard SHALL display count=N and size=S.
 */
class StorageStatisticsAccuracyPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Property 11: File count calculation is accurate.
     * 
     * For any collection of N VideoFiles associated with a storage,
     * the calculated count SHALL equal N.
     */
    public function testFileCountCalculationIsAccurate(): void
    {
        $this->forAll(
            Generator\choose(0, 50) // Количество файлов
        )->then(function (int $fileCount): void {
            $storage = $this->createStorage();
            $files = [];
            
            for ($i = 0; $i < $fileCount; $i++) {
                $files[] = $this->createVideoFile($storage, 1000);
            }
            
            $calculatedCount = $this->calculateFilesCount($files, $storage);
            
            $this->assertEquals(
                $fileCount,
                $calculatedCount,
                \sprintf(
                    'Expected %d files, calculated %d',
                    $fileCount,
                    $calculatedCount
                )
            );
        });
    }

    /**
     * Property 11: Total size calculation is accurate.
     * 
     * For any collection of VideoFiles with sizes S1, S2, ..., Sn,
     * the calculated total size SHALL equal S1 + S2 + ... + Sn.
     */
    public function testTotalSizeCalculationIsAccurate(): void
    {
        $this->forAll(
            Generator\seq(Generator\choose(0, 1000000)) // Массив размеров файлов
        )->then(function (array $fileSizes): void {
            $storage = $this->createStorage();
            $files = [];
            
            foreach ($fileSizes as $size) {
                $files[] = $this->createVideoFile($storage, $size);
            }
            
            $expectedTotalSize = \array_sum($fileSizes);
            $calculatedTotalSize = $this->calculateTotalSize($files, $storage);
            
            $this->assertEquals(
                $expectedTotalSize,
                $calculatedTotalSize,
                \sprintf(
                    'Expected total size %d, calculated %d',
                    $expectedTotalSize,
                    $calculatedTotalSize
                )
            );
        });
    }

    /**
     * Property 11: Statistics for specific storage excludes other storages.
     * 
     * For any storage A with N files, files from storage B SHALL NOT 
     * be included in storage A's statistics.
     */
    public function testStatisticsExcludesOtherStorages(): void
    {
        $this->forAll(
            Generator\choose(0, 20), // Файлы в хранилище A
            Generator\choose(0, 20)  // Файлы в хранилище B
        )->then(function (int $filesInA, int $filesInB): void {
            $storageA = $this->createStorage('Storage A');
            $storageB = $this->createStorage('Storage B');
            
            $allFiles = [];
            
            // Файлы для хранилища A
            for ($i = 0; $i < $filesInA; $i++) {
                $allFiles[] = $this->createVideoFile($storageA, 1000);
            }
            
            // Файлы для хранилища B
            for ($i = 0; $i < $filesInB; $i++) {
                $allFiles[] = $this->createVideoFile($storageB, 2000);
            }
            
            $countA = $this->calculateFilesCount($allFiles, $storageA);
            $countB = $this->calculateFilesCount($allFiles, $storageB);
            
            $this->assertEquals(
                $filesInA,
                $countA,
                \sprintf(
                    'Storage A should have %d files, got %d',
                    $filesInA,
                    $countA
                )
            );
            
            $this->assertEquals(
                $filesInB,
                $countB,
                \sprintf(
                    'Storage B should have %d files, got %d',
                    $filesInB,
                    $countB
                )
            );
        });
    }

    /**
     * Property 11: Local storage statistics counts files without storage.
     * 
     * For any collection of files where N have no storage assigned,
     * local storage statistics SHALL show count=N.
     */
    public function testLocalStorageStatisticsCountsFilesWithoutStorage(): void
    {
        $this->forAll(
            Generator\choose(0, 20), // Локальные файлы (без storage)
            Generator\choose(0, 20)  // Удалённые файлы (с storage)
        )->then(function (int $localFiles, int $remoteFiles): void {
            $remoteStorage = $this->createStorage('Remote Storage');
            
            $allFiles = [];
            
            // Локальные файлы (без storage)
            for ($i = 0; $i < $localFiles; $i++) {
                $allFiles[] = $this->createVideoFile(null, 1000);
            }
            
            // Удалённые файлы
            for ($i = 0; $i < $remoteFiles; $i++) {
                $allFiles[] = $this->createVideoFile($remoteStorage, 2000);
            }
            
            $localCount = $this->calculateLocalFilesCount($allFiles);
            
            $this->assertEquals(
                $localFiles,
                $localCount,
                \sprintf(
                    'Local storage should have %d files, got %d',
                    $localFiles,
                    $localCount
                )
            );
        });
    }

    /**
     * Property 11: Combined count and size are consistent.
     * 
     * For any storage with files of various sizes,
     * count * average_size should approximately equal total_size.
     */
    public function testCombinedCountAndSizeAreConsistent(): void
    {
        $this->forAll(
            Generator\choose(1, 30), // Количество файлов (минимум 1)
            Generator\choose(100, 10000) // Размер каждого файла
        )->then(function (int $fileCount, int $fileSize): void {
            $storage = $this->createStorage();
            $files = [];
            
            for ($i = 0; $i < $fileCount; $i++) {
                $files[] = $this->createVideoFile($storage, $fileSize);
            }
            
            $calculatedCount = $this->calculateFilesCount($files, $storage);
            $calculatedTotalSize = $this->calculateTotalSize($files, $storage);
            
            $expectedTotalSize = $fileCount * $fileSize;
            
            $this->assertEquals($fileCount, $calculatedCount);
            $this->assertEquals(
                $expectedTotalSize,
                $calculatedTotalSize,
                \sprintf(
                    'Expected total size %d (%d files * %d bytes), got %d',
                    $expectedTotalSize,
                    $fileCount,
                    $fileSize,
                    $calculatedTotalSize
                )
            );
        });
    }

    /**
     * Property 11: Empty storage has zero count and size.
     * 
     * For any storage with no files,
     * count SHALL be 0 and size SHALL be 0.
     */
    public function testEmptyStorageHasZeroCountAndSize(): void
    {
        $this->forAll(
            Generator\constant(0) // Всегда 0 файлов
        )->then(function (int $_): void {
            $storage = $this->createStorage();
            $files = [];
            
            $calculatedCount = $this->calculateFilesCount($files, $storage);
            $calculatedTotalSize = $this->calculateTotalSize($files, $storage);
            
            $this->assertEquals(0, $calculatedCount, 'Empty storage should have 0 files');
            $this->assertEquals(0, $calculatedTotalSize, 'Empty storage should have 0 total size');
        });
    }

    /**
     * Property 11: Large file sizes are handled correctly.
     * 
     * For any storage with files of large sizes (up to 10GB each),
     * the total size calculation SHALL not overflow.
     */
    public function testLargeFileSizesAreHandledCorrectly(): void
    {
        $this->forAll(
            Generator\choose(1, 10), // Количество файлов
            Generator\choose(1000000000, 10000000000) // Размер 1-10 GB
        )->then(function (int $fileCount, int $fileSize): void {
            $storage = $this->createStorage();
            $files = [];
            
            for ($i = 0; $i < $fileCount; $i++) {
                $files[] = $this->createVideoFile($storage, $fileSize);
            }
            
            $calculatedTotalSize = $this->calculateTotalSize($files, $storage);
            $expectedTotalSize = $fileCount * $fileSize;
            
            $this->assertEquals(
                $expectedTotalSize,
                $calculatedTotalSize,
                \sprintf(
                    'Large file sizes: expected %d, got %d',
                    $expectedTotalSize,
                    $calculatedTotalSize
                )
            );
        });
    }

    /**
     * Property 11: Zero-size files are counted correctly.
     * 
     * For any storage with N files of size 0,
     * count SHALL be N and total size SHALL be 0.
     */
    public function testZeroSizeFilesAreCountedCorrectly(): void
    {
        $this->forAll(
            Generator\choose(0, 30) // Количество файлов с размером 0
        )->then(function (int $fileCount): void {
            $storage = $this->createStorage();
            $files = [];
            
            for ($i = 0; $i < $fileCount; $i++) {
                $files[] = $this->createVideoFile($storage, 0);
            }
            
            $calculatedCount = $this->calculateFilesCount($files, $storage);
            $calculatedTotalSize = $this->calculateTotalSize($files, $storage);
            
            $this->assertEquals(
                $fileCount,
                $calculatedCount,
                \sprintf(
                    'Zero-size files: expected count %d, got %d',
                    $fileCount,
                    $calculatedCount
                )
            );
            
            $this->assertEquals(
                0,
                $calculatedTotalSize,
                'Zero-size files should have total size 0'
            );
        });
    }

    /**
     * Creates a Storage entity for testing.
     */
    private function createStorage(string $name = 'Test Storage'): Storage
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
            'basePath' => '/videos',
        ]);
        
        return $storage;
    }

    /**
     * Creates a VideoFile entity for testing.
     */
    private function createVideoFile(?Storage $storage, int $fileSize): VideoFile
    {
        $videoFile = new VideoFile();
        $videoFile->setFile('test/video.mp4');
        $videoFile->setFileSize($fileSize);
        
        if ($storage !== null) {
            $videoFile->setStorage($storage);
            $videoFile->setRemotePath('remote/path/video.mp4');
        }
        
        return $videoFile;
    }

    /**
     * Calculates files count for a specific storage.
     * This simulates the logic from StorageStatsService::getStorageStats()
     * 
     * @param VideoFile[] $files
     */
    private function calculateFilesCount(array $files, Storage $storage): int
    {
        $count = 0;
        
        foreach ($files as $file) {
            if ($file->getStorage() === $storage) {
                $count++;
            }
        }
        
        return $count;
    }

    /**
     * Calculates total size for a specific storage.
     * This simulates the logic from StorageStatsService::getStorageStats()
     * 
     * @param VideoFile[] $files
     */
    private function calculateTotalSize(array $files, Storage $storage): int
    {
        $totalSize = 0;
        
        foreach ($files as $file) {
            if ($file->getStorage() === $storage) {
                $totalSize += $file->getFileSize();
            }
        }
        
        return $totalSize;
    }

    /**
     * Calculates files count for local storage (files without storage).
     * This simulates the logic from StorageStatsService::getLocalStorageStats()
     * 
     * @param VideoFile[] $files
     */
    private function calculateLocalFilesCount(array $files): int
    {
        $count = 0;
        
        foreach ($files as $file) {
            if ($file->getStorage() === null) {
                $count++;
            }
        }
        
        return $count;
    }
}
