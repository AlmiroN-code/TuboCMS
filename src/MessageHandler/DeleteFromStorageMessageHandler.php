<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Storage;
use App\Message\DeleteFromStorageMessage;
use App\Repository\StorageRepository;
use App\Service\StorageManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Handler для асинхронного удаления файлов из удалённого хранилища.
 * 
 * Requirement 5.1: WHEN a video is deleted THEN the System SHALL 
 * queue deletion jobs for all associated remote files
 * 
 * Requirement 5.2: WHEN deleting from FTP storage THEN the System SHALL 
 * remove the file using FTP DELETE command
 * 
 * Requirement 5.3: WHEN deleting from SFTP storage THEN the System SHALL 
 * remove the file using SFTP unlink command
 * 
 * Requirement 5.4: WHEN deleting from Remote Server THEN the System SHALL 
 * send HTTP DELETE request to the configured endpoint
 * 
 * Requirement 5.5: IF deletion fails THEN the System SHALL log the error 
 * for manual cleanup
 */
#[AsMessageHandler]
class DeleteFromStorageMessageHandler
{
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly StorageRepository $storageRepository,
        private readonly StorageManager $storageManager,
        private readonly ManagerRegistry $doctrine,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(DeleteFromStorageMessage $message): void
    {
        $em = $this->getEntityManager();
        
        $storage = $em->getRepository(Storage::class)->find($message->getStorageId());
        
        if ($storage === null) {
            $this->logger->warning('Storage not found for deletion', [
                'storage_id' => $message->getStorageId(),
                'remote_path' => $message->getRemotePath(),
            ]);
            return;
        }

        $this->logger->info('Starting file deletion from storage', [
            'storage_id' => $message->getStorageId(),
            'storage_name' => $storage->getName(),
            'storage_type' => $storage->getType(),
            'remote_path' => $message->getRemotePath(),
            'attempt' => $message->getAttempt(),
        ]);

        try {
            $result = $this->storageManager->deleteFile($message->getRemotePath(), $storage);
            
            if ($result) {
                $this->logger->info('File deleted successfully from storage', [
                    'storage_id' => $message->getStorageId(),
                    'remote_path' => $message->getRemotePath(),
                ]);
            } else {
                $this->handleDeleteFailure($message, $storage, 'Delete operation returned false');
            }
        } catch (\Throwable $e) {
            $this->handleDeleteFailure($message, $storage, $e->getMessage());
        }
    }

    /**
     * Обрабатывает неудачную попытку удаления.
     */
    private function handleDeleteFailure(
        DeleteFromStorageMessage $message,
        Storage $storage,
        string $errorMessage
    ): void {
        $attempt = $message->getAttempt();
        
        $this->logger->warning('Delete attempt failed', [
            'storage_id' => $message->getStorageId(),
            'storage_name' => $storage->getName(),
            'remote_path' => $message->getRemotePath(),
            'attempt' => $attempt,
            'max_attempts' => self::MAX_ATTEMPTS,
            'error' => $errorMessage,
        ]);

        if ($attempt < self::MAX_ATTEMPTS) {
            $this->logger->info('Scheduling delete retry', [
                'storage_id' => $message->getStorageId(),
                'remote_path' => $message->getRemotePath(),
                'next_attempt' => $attempt + 1,
            ]);

            // Создаём новое сообщение для повторной попытки
            $retryMessage = $message->retry();
            $this->messageBus->dispatch($retryMessage);
        } else {
            // Все попытки исчерпаны - логируем для ручной очистки
            // Requirement 5.5: IF deletion fails THEN the System SHALL log 
            // the error for manual cleanup
            $this->logger->error('All delete attempts failed, manual cleanup required', [
                'storage_id' => $message->getStorageId(),
                'storage_name' => $storage->getName(),
                'storage_type' => $storage->getType(),
                'remote_path' => $message->getRemotePath(),
                'error' => $errorMessage,
            ]);
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
