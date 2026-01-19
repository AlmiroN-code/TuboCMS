<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Storage;
use App\Entity\VideoFile;
use App\Message\MigrateFileMessage;
use App\Service\MigrationReportService;
use App\Service\StorageManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Handler для асинхронной миграции файлов между хранилищами.
 * 
 * Requirement 4.2: WHEN migration is started THEN the System SHALL 
 * queue migration jobs for each video file
 * 
 * Requirement 4.3: WHEN a file is migrated THEN the System SHALL copy the file 
 * to destination, verify integrity, and update the VideoFile record
 * 
 * Requirement 4.4: IF migration fails for a file THEN the System SHALL 
 * log the error and continue with remaining files
 * 
 * Requirement 4.5: WHEN migration completes THEN the System SHALL provide 
 * a summary report with success/failure counts
 */
#[AsMessageHandler]
class MigrateFileMessageHandler
{
    private const int MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly StorageManager $storageManager,
        private readonly ManagerRegistry $doctrine,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly MigrationReportService $migrationReportService,
    ) {
    }

    public function __invoke(MigrateFileMessage $message): void
    {
        $em = $this->getEntityManager();
        
        $videoFile = $em->getRepository(VideoFile::class)->find($message->getVideoFileId());
        
        if ($videoFile === null) {
            $this->logger->warning('VideoFile not found for migration', [
                'video_file_id' => $message->getVideoFileId(),
            ]);
            return;
        }

        // destinationStorageId = 0 означает миграцию в локальное хранилище
        $destinationStorageId = $message->getDestinationStorageId();
        $destinationStorage = null;
        
        if ($destinationStorageId > 0) {
            $destinationStorage = $em->getRepository(Storage::class)->find($destinationStorageId);
            
            if ($destinationStorage === null) {
                $this->logger->warning('Destination storage not found for migration', [
                    'video_file_id' => $message->getVideoFileId(),
                    'destination_storage_id' => $destinationStorageId,
                ]);
                return;
            }

            // Проверяем, что целевое хранилище включено
            if (!$destinationStorage->isEnabled()) {
                $this->logger->error('Cannot migrate to disabled storage', [
                    'video_file_id' => $message->getVideoFileId(),
                    'destination_storage_id' => $destinationStorage->getId(),
                    'destination_storage_name' => $destinationStorage->getName(),
                ]);
                return;
            }
        }

        $this->logger->info('Starting file migration', [
            'video_file_id' => $message->getVideoFileId(),
            'source_storage_id' => $videoFile->getStorage()?->getId(),
            'destination_storage_id' => $destinationStorageId,
            'destination_type' => $destinationStorage === null ? 'local' : 'remote',
            'attempt' => $message->getAttempt(),
        ]);

        try {
            $result = $this->storageManager->migrateFile($videoFile, $destinationStorage);
            
            if ($result) {
                // Сохраняем изменения в VideoFile
                $em->flush();
                
                // Записываем успех в отчёт миграции
                // Requirement 4.5: provide a summary report with success/failure counts
                $migrationId = $message->getMigrationId();
                if ($migrationId !== null) {
                    $this->migrationReportService->recordSuccess($migrationId, $message->getVideoFileId());
                }
                
                $this->logger->info('File migrated successfully', [
                    'video_file_id' => $message->getVideoFileId(),
                    'destination_storage_id' => $destinationStorageId,
                    'new_remote_path' => $videoFile->getRemotePath(),
                    'new_local_path' => $videoFile->getFile(),
                    'migration_id' => $migrationId,
                ]);
            } else {
                $this->handleMigrationFailure($message, $videoFile, $destinationStorage, 'Migration returned false');
            }
        } catch (\Throwable $e) {
            $this->handleMigrationFailure($message, $videoFile, $destinationStorage, $e->getMessage());
        }
    }

    /**
     * Обрабатывает неудачную попытку миграции.
     * 
     * Requirement 4.4: IF migration fails for a file THEN the System SHALL 
     * log the error and continue with remaining files
     * 
     * Requirement 4.5: WHEN migration completes THEN the System SHALL provide 
     * a summary report with success/failure counts
     */
    private function handleMigrationFailure(
        MigrateFileMessage $message,
        VideoFile $videoFile,
        ?Storage $destinationStorage,
        string $errorMessage
    ): void {
        $attempt = $message->getAttempt();
        $destinationName = $destinationStorage?->getName() ?? 'Локальное хранилище';
        $migrationId = $message->getMigrationId();
        
        $this->logger->warning('Migration attempt failed', [
            'video_file_id' => $message->getVideoFileId(),
            'source_storage_id' => $videoFile->getStorage()?->getId(),
            'destination_storage_id' => $message->getDestinationStorageId(),
            'destination_name' => $destinationName,
            'attempt' => $attempt,
            'max_attempts' => self::MAX_ATTEMPTS,
            'error' => $errorMessage,
            'migration_id' => $migrationId,
        ]);

        if ($attempt < self::MAX_ATTEMPTS) {
            $this->logger->info('Scheduling migration retry', [
                'video_file_id' => $message->getVideoFileId(),
                'next_attempt' => $attempt + 1,
            ]);

            // Создаём новое сообщение для повторной попытки
            $retryMessage = $message->retry();
            $this->messageBus->dispatch($retryMessage);
        } else {
            // Все попытки исчерпаны - логируем ошибку и записываем в отчёт
            // Requirement 4.4: IF migration fails for a file THEN the System SHALL 
            // log the error and continue with remaining files
            // Requirement 4.5: provide a summary report with success/failure counts
            $this->logger->error('All migration attempts failed', [
                'video_file_id' => $message->getVideoFileId(),
                'video_id' => $videoFile->getVideo()?->getId(),
                'source_storage_id' => $videoFile->getStorage()?->getId(),
                'destination_storage_id' => $message->getDestinationStorageId(),
                'destination_name' => $destinationName,
                'error' => $errorMessage,
                'migration_id' => $migrationId,
            ]);
            
            // Записываем ошибку в отчёт миграции
            if ($migrationId !== null) {
                $this->migrationReportService->recordFailure(
                    $migrationId,
                    $message->getVideoFileId(),
                    $errorMessage
                );
            }
        }
    }

    /**
     * Получает EntityManager, создавая новый если текущий закрыт.
     */
    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = $this->doctrine->getManager();
        
        if ($em instanceof EntityManagerInterface && !$em->isOpen()) {
            $this->doctrine->resetManager();
            /** @var EntityManagerInterface $em */
            $em = $this->doctrine->getManager();
        }
        
        return $em;
    }
}
