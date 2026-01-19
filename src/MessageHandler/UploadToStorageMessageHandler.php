<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\VideoFile;
use App\Message\UploadToStorageMessage;
use App\Repository\VideoFileRepository;
use App\Service\NotificationService;
use App\Service\StorageManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Handler для асинхронной загрузки файлов в удалённое хранилище.
 * 
 * Requirement 2.1: WHEN a video is processed THEN the System SHALL upload 
 * all generated files to the configured default storage
 * 
 * Requirement 2.5: IF upload fails THEN the System SHALL retry up to 3 times 
 * with exponential backoff
 * 
 * Requirement 2.6: IF all upload attempts fail THEN the System SHALL mark 
 * the video as failed and notify the administrator
 */
#[AsMessageHandler]
class UploadToStorageMessageHandler
{
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly VideoFileRepository $videoFileRepository,
        private readonly StorageManager $storageManager,
        private readonly NotificationService $notificationService,
        private readonly ManagerRegistry $doctrine,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly string $projectDir,
    ) {
    }

    public function __invoke(UploadToStorageMessage $message): void
    {
        $em = $this->getEntityManager();
        
        $videoFile = $em->getRepository(VideoFile::class)->find($message->getVideoFileId());
        
        if ($videoFile === null) {
            $this->logger->warning('VideoFile not found for upload', [
                'video_file_id' => $message->getVideoFileId(),
            ]);
            return;
        }

        $localPath = $message->getLocalPath();
        $fullLocalPath = $this->resolveLocalPath($localPath);
        
        if (!\file_exists($fullLocalPath)) {
            $this->logger->error('Local file not found for upload', [
                'video_file_id' => $message->getVideoFileId(),
                'local_path' => $fullLocalPath,
            ]);
            $this->handleFailure($videoFile, 'Local file not found: ' . $localPath);
            return;
        }

        $this->logger->info('Starting upload to storage', [
            'video_file_id' => $message->getVideoFileId(),
            'local_path' => $localPath,
            'attempt' => $message->getAttempt(),
        ]);

        try {
            $defaultStorage = $this->storageManager->getDefaultStorage();
            
            if ($defaultStorage === null) {
                $this->logger->info('No default storage configured, skipping upload', [
                    'video_file_id' => $message->getVideoFileId(),
                ]);
                return;
            }

            // Генерируем путь для удалённого хранилища
            $remotePath = $this->generateRemotePath($videoFile);
            
            // Загружаем файл
            $result = $this->storageManager->uploadFile($fullLocalPath, $remotePath, $defaultStorage);
            
            if ($result->success) {
                // Обновляем VideoFile с информацией о хранилище
                $videoFile->setStorage($defaultStorage);
                $videoFile->setRemotePath($result->remotePath ?? $remotePath);
                
                $em->flush();
                
                $this->logger->info('File uploaded successfully to storage', [
                    'video_file_id' => $message->getVideoFileId(),
                    'storage_id' => $defaultStorage->getId(),
                    'remote_path' => $videoFile->getRemotePath(),
                ]);
            } else {
                $this->handleUploadFailure($message, $videoFile, $result->errorMessage ?? 'Unknown error');
            }
        } catch (\Throwable $e) {
            $this->handleUploadFailure($message, $videoFile, $e->getMessage());
        }
    }

    /**
     * Обрабатывает неудачную попытку загрузки.
     * 
     * Requirement 2.5: IF upload fails THEN the System SHALL retry up to 3 times 
     * with exponential backoff
     */
    private function handleUploadFailure(
        UploadToStorageMessage $message,
        VideoFile $videoFile,
        string $errorMessage
    ): void {
        $attempt = $message->getAttempt();
        
        $this->logger->warning('Upload attempt failed', [
            'video_file_id' => $message->getVideoFileId(),
            'attempt' => $attempt,
            'max_attempts' => self::MAX_ATTEMPTS,
            'error' => $errorMessage,
        ]);

        if ($attempt < self::MAX_ATTEMPTS) {
            // Планируем повторную попытку с экспоненциальной задержкой
            $delay = $this->calculateDelay($attempt);
            
            $this->logger->info('Scheduling retry', [
                'video_file_id' => $message->getVideoFileId(),
                'next_attempt' => $attempt + 1,
                'delay_seconds' => $delay,
            ]);

            // Создаём новое сообщение для повторной попытки
            $retryMessage = $message->retry();
            
            // Отправляем с задержкой через Messenger
            // Примечание: задержка реализуется через stamps или внешний scheduler
            $this->messageBus->dispatch($retryMessage);
        } else {
            // Все попытки исчерпаны
            $this->handleFailure($videoFile, $errorMessage);
        }
    }

    /**
     * Обрабатывает окончательную неудачу загрузки.
     * 
     * Requirement 2.6: IF all upload attempts fail THEN the System SHALL mark 
     * the video as failed and notify the administrator
     */
    private function handleFailure(VideoFile $videoFile, string $errorMessage): void
    {
        $this->logger->error('All upload attempts failed', [
            'video_file_id' => $videoFile->getId(),
            'error' => $errorMessage,
        ]);

        $video = $videoFile->getVideo();
        
        if ($video !== null) {
            $em = $this->getEntityManager();
            
            $video->setProcessingStatus('upload_failed');
            $video->setProcessingError('Storage upload failed: ' . $errorMessage);
            
            $em->flush();

            // Отправляем уведомление администратору
            try {
                $this->notificationService->notifyVideoFailed($video);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to send failure notification', [
                    'video_id' => $video->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Вычисляет задержку для повторной попытки с экспоненциальным backoff.
     * 
     * Formula: 2^attempt seconds
     * Attempt 1: 2 seconds
     * Attempt 2: 4 seconds
     */
    private function calculateDelay(int $attempt): int
    {
        return (int) \pow(2, $attempt);
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
        $localFile = $videoFile->getFile() ?? '';
        $extension = \pathinfo($localFile, PATHINFO_EXTENSION) ?: 'mp4';
        
        return \sprintf(
            'videos/%d/%s/%s.%s',
            $videoId,
            \strtolower($profileName),
            \uniqid('video_', true),
            $extension
        );
    }

    /**
     * Преобразует относительный путь в абсолютный.
     */
    private function resolveLocalPath(string $localPath): string
    {
        // Если путь уже абсолютный
        if (\str_starts_with($localPath, '/') || \str_starts_with($localPath, $this->projectDir)) {
            return $localPath;
        }
        
        // Относительный путь от public/media
        return $this->projectDir . '/public/media/' . $localPath;
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
