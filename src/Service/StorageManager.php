<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Storage;
use App\Entity\VideoFile;
use App\Repository\StorageRepository;
use App\Service\SignedUrlService;
use App\Storage\DTO\UploadResult;
use App\Storage\Factory\StorageAdapterFactoryInterface;
use App\Storage\StorageAdapterInterface;
use Psr\Log\LoggerInterface;

/**
 * Менеджер хранилищ для управления файлами на различных storage backends.
 * 
 * Validates: Requirements 1.7, 2.1, 3.1, 4.3
 */
class StorageManager
{
    /**
     * @var StorageAdapterFactoryInterface[]
     */
    private array $factories = [];

    /**
     * @var array<int, StorageAdapterInterface>
     */
    private array $adapterCache = [];

    public function __construct(
        private readonly StorageRepository $storageRepository,
        private readonly LoggerInterface $logger,
        iterable $factories,
    ) {
        foreach ($factories as $factory) {
            $this->factories[] = $factory;
        }
    }

    /**
     * Получить адаптер для указанного хранилища.
     * 
     * @throws \InvalidArgumentException Если тип хранилища не поддерживается
     */
    public function getAdapter(Storage $storage): StorageAdapterInterface
    {
        $storageId = $storage->getId();
        
        // Возвращаем кэшированный адаптер если есть
        if ($storageId !== null && isset($this->adapterCache[$storageId])) {
            return $this->adapterCache[$storageId];
        }

        foreach ($this->factories as $factory) {
            if ($factory->supports($storage)) {
                $adapter = $factory->create($storage);
                
                // Кэшируем адаптер для повторного использования
                if ($storageId !== null) {
                    $this->adapterCache[$storageId] = $adapter;
                }
                
                return $adapter;
            }
        }

        throw new \InvalidArgumentException(
            \sprintf('No factory found for storage type: %s', $storage->getType())
        );
    }


    /**
     * Получить хранилище по умолчанию.
     * 
     * Requirement 1.7: WHEN an administrator sets a storage as default 
     * THEN the System SHALL use this storage for all new video uploads
     */
    public function getDefaultStorage(): ?Storage
    {
        return $this->storageRepository->findDefault();
    }

    /**
     * Загрузить файл в хранилище.
     * 
     * Requirement 1.8: WHEN an administrator disables a storage 
     * THEN the System SHALL prevent new uploads to this storage
     * 
     * Requirement 2.1: WHEN a video is processed THEN the System SHALL upload 
     * all generated files to the configured default storage
     * 
     * @param string $localPath Путь к локальному файлу
     * @param string $remotePath Путь на удалённом хранилище
     * @param Storage|null $storage Хранилище (если null - используется default)
     * @return UploadResult Результат загрузки
     * @throws \RuntimeException Если хранилище отключено или не найдено
     */
    public function uploadFile(string $localPath, string $remotePath, ?Storage $storage = null): UploadResult
    {
        // Если хранилище не указано, используем default
        if ($storage === null) {
            $storage = $this->getDefaultStorage();
            
            if ($storage === null) {
                $this->logger->error('No default storage configured for upload');
                return UploadResult::failure('No default storage configured');
            }
        }

        // Проверяем, что хранилище включено
        if (!$storage->isEnabled()) {
            $this->logger->warning('Attempted upload to disabled storage', [
                'storage_id' => $storage->getId(),
                'storage_name' => $storage->getName(),
            ]);
            throw new \RuntimeException(
                \sprintf('Storage "%s" is disabled and cannot accept uploads', $storage->getName())
            );
        }

        try {
            $adapter = $this->getAdapter($storage);
            
            // Создаём директорию если нужно
            $directory = \dirname($remotePath);
            if ($directory !== '.' && $directory !== '/') {
                $adapter->createDirectory($directory);
            }
            
            $result = $adapter->upload($localPath, $remotePath);
            
            if ($result->success) {
                $this->logger->info('File uploaded successfully', [
                    'storage_id' => $storage->getId(),
                    'local_path' => $localPath,
                    'remote_path' => $remotePath,
                ]);
            } else {
                $this->logger->error('File upload failed', [
                    'storage_id' => $storage->getId(),
                    'local_path' => $localPath,
                    'remote_path' => $remotePath,
                    'error' => $result->errorMessage,
                ]);
            }
            
            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('File upload exception', [
                'storage_id' => $storage->getId(),
                'local_path' => $localPath,
                'remote_path' => $remotePath,
                'error' => $e->getMessage(),
            ]);
            
            return UploadResult::failure($e->getMessage());
        }
    }


    /**
     * Удалить файл из хранилища.
     * 
     * Requirement 5.1: WHEN a video is deleted THEN the System SHALL 
     * queue deletion jobs for all associated remote files
     * 
     * @param string $remotePath Путь к файлу на хранилище
     * @param Storage $storage Хранилище
     * @return bool True если удаление успешно
     */
    public function deleteFile(string $remotePath, Storage $storage): bool
    {
        try {
            $adapter = $this->getAdapter($storage);
            $result = $adapter->delete($remotePath);
            
            if ($result) {
                $this->logger->info('File deleted successfully', [
                    'storage_id' => $storage->getId(),
                    'remote_path' => $remotePath,
                ]);
            } else {
                $this->logger->warning('File deletion returned false', [
                    'storage_id' => $storage->getId(),
                    'remote_path' => $remotePath,
                ]);
            }
            
            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('File deletion failed', [
                'storage_id' => $storage->getId(),
                'remote_path' => $remotePath,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Получить URL для файла.
     * 
     * Requirement 3.1: WHEN a user requests a video THEN the System SHALL 
     * generate appropriate URL based on storage type
     * 
     * Requirement 3.3: WHEN video is stored on Remote Server THEN the System 
     * SHALL return the direct URL to the remote file
     * 
     * @param VideoFile $videoFile Файл видео
     * @return string URL для доступа к файлу
     */
    public function getFileUrl(VideoFile $videoFile): string
    {
        $storage = $videoFile->getStorage();
        
        // Если файл не на удалённом хранилище, возвращаем локальный путь
        if ($storage === null || $videoFile->getRemotePath() === null) {
            return $videoFile->getFile() ?? '';
        }

        try {
            $adapter = $this->getAdapter($storage);
            return $adapter->getUrl($videoFile->getRemotePath());
        } catch (\Throwable $e) {
            $this->logger->error('Failed to get file URL', [
                'video_file_id' => $videoFile->getId(),
                'storage_id' => $storage->getId(),
                'error' => $e->getMessage(),
            ]);
            
            // Fallback к локальному пути
            return $videoFile->getFile() ?? '';
        }
    }

    /**
     * Получить подписанный URL для файла.
     * 
     * Requirement 3.4: WHEN generating video URLs THEN the System SHALL 
     * support optional signed URLs with expiration for security
     * 
     * Property 7: Signed URLs contain signature and expiration
     * For any signed URL generated with expiration time T, the URL SHALL contain 
     * a signature parameter and expire parameter with value T.
     * 
     * @param VideoFile $videoFile Файл видео
     * @param int $expiresIn Время жизни URL в секундах
     * @return string Подписанный URL
     */
    public function getSignedFileUrl(VideoFile $videoFile, int $expiresIn = 3600): string
    {
        $storage = $videoFile->getStorage();
        
        if ($storage === null || $videoFile->getRemotePath() === null) {
            return $videoFile->getFile() ?? '';
        }

        try {
            $adapter = $this->getAdapter($storage);
            return $adapter->getSignedUrl($videoFile->getRemotePath(), $expiresIn);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to get signed file URL', [
                'video_file_id' => $videoFile->getId(),
                'storage_id' => $storage->getId(),
                'error' => $e->getMessage(),
            ]);
            
            return $videoFile->getFile() ?? '';
        }
    }

    /**
     * Получить подписанный URL для файла с использованием SignedUrlService.
     * 
     * Этот метод использует централизованный сервис подписи для генерации URL,
     * что обеспечивает единообразие подписей и возможность верификации.
     * 
     * @param VideoFile $videoFile Файл видео
     * @param SignedUrlService $signedUrlService Сервис подписи URL
     * @param int $expiresIn Время жизни URL в секундах
     * @return string Подписанный URL
     */
    public function getSignedFileUrlWithService(
        VideoFile $videoFile, 
        SignedUrlService $signedUrlService,
        int $expiresIn = 3600
    ): string {
        $storage = $videoFile->getStorage();
        
        if ($storage === null || $videoFile->getRemotePath() === null) {
            return $videoFile->getFile() ?? '';
        }

        try {
            $adapter = $this->getAdapter($storage);
            $baseUrl = $adapter->getUrl($videoFile->getRemotePath());
            
            return $signedUrlService->generateSignedUrlForVideoFile(
                $videoFile, 
                $baseUrl, 
                $expiresIn
            );
        } catch (\Throwable $e) {
            $this->logger->error('Failed to get signed file URL with service', [
                'video_file_id' => $videoFile->getId(),
                'storage_id' => $storage->getId(),
                'error' => $e->getMessage(),
            ]);
            
            return $videoFile->getFile() ?? '';
        }
    }


    /**
     * Мигрировать файл между хранилищами.
     * 
     * Requirement 4.3: WHEN a file is migrated THEN the System SHALL copy the file 
     * to destination, verify integrity, and update the VideoFile record
     * 
     * @param VideoFile $videoFile Файл для миграции
     * @param Storage|null $destination Целевое хранилище (null = локальное)
     * @return bool True если миграция успешна
     */
    public function migrateFile(VideoFile $videoFile, ?Storage $destination): bool
    {
        $sourceStorage = $videoFile->getStorage();
        $sourcePath = $videoFile->getRemotePath();
        
        // Миграция в локальное хранилище
        if ($destination === null) {
            return $this->migrateToLocal($videoFile, $sourceStorage, $sourcePath);
        }
        
        // Если файл локальный, используем локальный путь
        if ($sourceStorage === null || $sourcePath === null) {
            $localPath = $videoFile->getFile();
            if ($localPath === null) {
                $this->logger->error('Cannot migrate file: no source path', [
                    'video_file_id' => $videoFile->getId(),
                ]);
                return false;
            }
            
            // Генерируем путь для удалённого хранилища
            $remotePath = $this->generateRemotePath($videoFile);
            
            $result = $this->uploadFile($localPath, $remotePath, $destination);
            
            if ($result->success) {
                $videoFile->setStorage($destination);
                $videoFile->setRemotePath($result->remotePath);
                
                $this->logger->info('File migrated from local to remote', [
                    'video_file_id' => $videoFile->getId(),
                    'destination_storage_id' => $destination->getId(),
                    'remote_path' => $result->remotePath,
                ]);
                
                return true;
            }
            
            return false;
        }

        // Миграция между удалёнными хранилищами
        try {
            $sourceAdapter = $this->getAdapter($sourceStorage);
            
            // Создаём временный файл для скачивания
            $tempFile = \sys_get_temp_dir() . '/' . \uniqid('migration_', true);
            
            // Скачиваем файл из источника
            if (!$sourceAdapter->download($sourcePath, $tempFile)) {
                $this->logger->error('Failed to download file for migration', [
                    'video_file_id' => $videoFile->getId(),
                    'source_storage_id' => $sourceStorage->getId(),
                    'source_path' => $sourcePath,
                ]);
                return false;
            }

            // Генерируем путь для целевого хранилища
            $destinationPath = $this->generateRemotePath($videoFile);
            
            // Загружаем в целевое хранилище
            $result = $this->uploadFile($tempFile, $destinationPath, $destination);
            
            // Удаляем временный файл
            if (\file_exists($tempFile)) {
                \unlink($tempFile);
            }
            
            if ($result->success) {
                $videoFile->setStorage($destination);
                $videoFile->setRemotePath($result->remotePath);
                
                $this->logger->info('File migrated between storages', [
                    'video_file_id' => $videoFile->getId(),
                    'source_storage_id' => $sourceStorage->getId(),
                    'destination_storage_id' => $destination->getId(),
                    'remote_path' => $result->remotePath,
                ]);
                
                return true;
            }
            
            return false;
        } catch (\Throwable $e) {
            $this->logger->error('File migration failed', [
                'video_file_id' => $videoFile->getId(),
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Мигрировать файл из удалённого хранилища в локальное.
     * 
     * @param VideoFile $videoFile Файл для миграции
     * @param Storage|null $sourceStorage Исходное хранилище
     * @param string|null $sourcePath Путь на исходном хранилище
     * @return bool True если миграция успешна
     */
    private function migrateToLocal(VideoFile $videoFile, ?Storage $sourceStorage, ?string $sourcePath): bool
    {
        // Если файл уже локальный, ничего не делаем
        if ($sourceStorage === null || $sourcePath === null) {
            $this->logger->info('File is already local, skipping migration', [
                'video_file_id' => $videoFile->getId(),
            ]);
            return true;
        }

        try {
            $sourceAdapter = $this->getAdapter($sourceStorage);
            
            // Генерируем локальный путь для файла
            $localPath = $this->generateLocalPath($videoFile);
            
            // Создаём директорию если нужно
            $directory = \dirname($localPath);
            if (!\is_dir($directory)) {
                \mkdir($directory, 0755, true);
            }
            
            // Скачиваем файл из удалённого хранилища
            if (!$sourceAdapter->download($sourcePath, $localPath)) {
                $this->logger->error('Failed to download file for local migration', [
                    'video_file_id' => $videoFile->getId(),
                    'source_storage_id' => $sourceStorage->getId(),
                    'source_path' => $sourcePath,
                ]);
                return false;
            }
            
            // Обновляем VideoFile
            $videoFile->setStorage(null);
            $videoFile->setRemotePath(null);
            $videoFile->setFile($localPath);
            
            $this->logger->info('File migrated from remote to local', [
                'video_file_id' => $videoFile->getId(),
                'source_storage_id' => $sourceStorage->getId(),
                'local_path' => $localPath,
            ]);
            
            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Migration to local failed', [
                'video_file_id' => $videoFile->getId(),
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Генерирует локальный путь для файла.
     */
    private function generateLocalPath(VideoFile $videoFile): string
    {
        $video = $videoFile->getVideo();
        $profile = $videoFile->getProfile();
        
        $videoId = $video?->getId() ?? 0;
        $profileName = $profile?->getName() ?? 'default';
        $extension = \pathinfo($videoFile->getRemotePath() ?? $videoFile->getFile() ?? '', PATHINFO_EXTENSION) ?: 'mp4';
        
        return \sprintf(
            'public/media/videos/%s/%d_%s.%s',
            $profileName,
            $videoId,
            \uniqid('', true),
            $extension
        );
    }

    /**
     * Генерирует путь для файла на удалённом хранилище.
     */
    private function generateRemotePath(VideoFile $videoFile): string
    {
        $video = $videoFile->getVideo();
        $profile = $videoFile->getProfile();
        
        $videoId = $video?->getId() ?? 0;
        $profileName = $profile?->getName() ?? 'default';
        $extension = \pathinfo($videoFile->getFile() ?? '', PATHINFO_EXTENSION) ?: 'mp4';
        
        return \sprintf(
            'videos/%d/%s/%s.%s',
            $videoId,
            $profileName,
            \uniqid('video_', true),
            $extension
        );
    }

    /**
     * Проверить существование файла на хранилище.
     */
    public function fileExists(string $remotePath, Storage $storage): bool
    {
        try {
            $adapter = $this->getAdapter($storage);
            return $adapter->exists($remotePath);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to check file existence', [
                'storage_id' => $storage->getId(),
                'remote_path' => $remotePath,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Скачать файл из хранилища.
     */
    public function downloadFile(string $remotePath, string $localPath, Storage $storage): bool
    {
        try {
            $adapter = $this->getAdapter($storage);
            $result = $adapter->download($remotePath, $localPath);
            
            if ($result) {
                $this->logger->info('File downloaded successfully', [
                    'storage_id' => $storage->getId(),
                    'remote_path' => $remotePath,
                    'local_path' => $localPath,
                ]);
            }
            
            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('File download failed', [
                'storage_id' => $storage->getId(),
                'remote_path' => $remotePath,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Очистить кэш адаптеров.
     */
    public function clearAdapterCache(): void
    {
        $this->adapterCache = [];
    }
}
