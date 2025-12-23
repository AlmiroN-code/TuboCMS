<?php

namespace App\MessageHandler;

use App\Entity\VideoFile;
use App\Message\ProcessVideoEncodingMessage;
use App\Repository\VideoRepository;
use App\Repository\VideoEncodingProfileRepository;
use App\Service\VideoProcessingService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;

#[AsMessageHandler]
class ProcessVideoEncodingMessageHandler
{
    public function __construct(
        private VideoRepository $videoRepository,
        private VideoEncodingProfileRepository $profileRepository,
        private VideoProcessingService $videoProcessor,
        private NotificationService $notificationService,
        private ManagerRegistry $doctrine,
        private LoggerInterface $logger,
        private Filesystem $filesystem,
        private string $projectDir
    ) {
    }

    public function __invoke(ProcessVideoEncodingMessage $message): void
    {
        // Получаем свежий EntityManager
        $em = $this->doctrine->getManager();
        
        if (!$em->isOpen()) {
            // Если EntityManager закрыт, создаем новый
            $this->doctrine->resetManager();
            $em = $this->doctrine->getManager();
        }

        $video = $em->getRepository(\App\Entity\Video::class)->find($message->getVideoId());
        
        if (!$video || !$video->getTempVideoFile()) {
            $this->logger->warning('Video not found or no temp file', ['videoId' => $message->getVideoId()]);
            return;
        }

        try {
            $this->logger->info('Starting video encoding', ['videoId' => $video->getId(), 'title' => $video->getTitle()]);
            
            // Начинаем транзакцию
            $em->beginTransaction();
            
            // Устанавливаем статус обработки
            $video->setProcessingStatus('processing');
            $video->setProcessingProgress(5);
            $em->flush();

            $tempVideoPath = $this->projectDir . '/public/media/' . $video->getTempVideoFile();
            
            if (!file_exists($tempVideoPath)) {
                throw new \Exception("Temporary video file not found: {$tempVideoPath}");
            }

            // Получаем активные профили кодирования
            $profiles = $em->getRepository(\App\Entity\VideoEncodingProfile::class)->findBy(['isActive' => true], ['orderPosition' => 'ASC']);
            
            if (empty($profiles)) {
                throw new \Exception('No active encoding profiles found');
            }

            $this->logger->info('Found encoding profiles', ['count' => count($profiles)]);

            // Сначала создаем постер и превью
            $mediaDir = $this->projectDir . '/public/media';
            $basicResult = $this->videoProcessor->processVideo($tempVideoPath, $mediaDir);
            
            if ($basicResult['success']) {
                $video->setDuration($basicResult['duration']);
                $video->setResolution($basicResult['resolution']);
                
                if ($basicResult['poster']) {
                    $video->setPoster($basicResult['poster']);
                }
                
                if ($basicResult['preview']) {
                    $video->setPreview($basicResult['preview']);
                }
            }

            $video->setProcessingProgress(20);
            $em->flush();

            // Кодируем видео в разные качества
            $totalProfiles = count($profiles);
            $processedProfiles = 0;
            $isPrimarySet = false;

            foreach ($profiles as $profile) {
                try {
                    $this->logger->info('Processing profile', [
                        'profile' => $profile->getName(),
                        'resolution' => $profile->getResolution()
                    ]);

                    // Создаем директорию для качества
                    $qualityDir = $mediaDir . '/videos/' . strtolower($profile->getName());
                    if (!is_dir($qualityDir)) {
                        mkdir($qualityDir, 0777, true);
                    }

                    // Генерируем имя файла
                    $outputFilename = $video->getId() . '_' . strtolower($profile->getName()) . '.mp4';
                    $outputPath = $qualityDir . '/' . $outputFilename;

                    // Кодируем видео
                    $success = $this->videoProcessor->encodeToProfile(
                        $tempVideoPath,
                        $outputPath,
                        $profile
                    );

                    if ($success && file_exists($outputPath)) {
                        // Создаем запись VideoFile
                        $videoFile = new VideoFile();
                        $videoFile->setVideo($video);
                        $videoFile->setProfile($profile);
                        $videoFile->setFile('videos/' . strtolower($profile->getName()) . '/' . $outputFilename);
                        $videoFile->setFileSize(filesize($outputPath));
                        $videoFile->setDuration($video->getDuration());
                        
                        // Первый профиль помечаем как основной
                        if (!$isPrimarySet) {
                            $videoFile->setPrimary(true);
                            $isPrimarySet = true;
                        }

                        $em->persist($videoFile);
                        $processedProfiles++;

                        $this->logger->info('Profile encoded successfully', [
                            'profile' => $profile->getName(),
                            'fileSize' => filesize($outputPath)
                        ]);
                    } else {
                        $this->logger->error('Failed to encode profile', [
                            'profile' => $profile->getName(),
                            'outputPath' => $outputPath
                        ]);
                    }

                    // Обновляем прогресс
                    $progress = 20 + (($processedProfiles / $totalProfiles) * 70);
                    $video->setProcessingProgress((int) $progress);
                    $em->flush();

                } catch (\Exception $e) {
                    $this->logger->error('Error processing profile', [
                        'profile' => $profile->getName(),
                        'error' => $e->getMessage()
                    ]);
                }
            }

            if ($processedProfiles > 0) {
                // Безопасно удаляем временный файл
                try {
                    if ($this->filesystem->exists($tempVideoPath)) {
                        $this->filesystem->remove($tempVideoPath);
                        $video->setTempVideoFile(null);
                        $this->logger->info('Temporary video file removed', ['path' => $tempVideoPath]);
                    }
                } catch (\Exception $e) {
                    $this->logger->warning('Failed to remove temporary video file', [
                        'path' => $tempVideoPath,
                        'error' => $e->getMessage()
                    ]);
                }

                $video->setStatus(\App\Entity\Video::STATUS_PUBLISHED);
                $video->setProcessingStatus('completed');
                $video->setProcessingProgress(100);
                $video->setProcessingError(null);
                
                $this->logger->info('Video encoding completed successfully', [
                    'videoId' => $video->getId(),
                    'processedProfiles' => $processedProfiles
                ]);

                // Завершаем транзакцию
                $em->flush();
                $em->commit();

                // Отправляем уведомление об успехе (вне транзакции)
                try {
                    $this->notificationService->notifyVideoProcessed($video);
                } catch (\Exception $notificationError) {
                    $this->logger->error('Failed to send success notification', [
                        'error' => $notificationError->getMessage()
                    ]);
                }
            } else {
                throw new \Exception('No profiles were processed successfully');
            }

        } catch (\Exception $e) {
            $this->logger->error('Video encoding failed', [
                'videoId' => $video->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Получаем свежий EntityManager для обработки ошибки
            $errorEm = $this->doctrine->getManager();
            if (!$errorEm->isOpen()) {
                $this->doctrine->resetManager();
                $errorEm = $this->doctrine->getManager();
            }

            // Откатываем транзакцию при ошибке
            if ($errorEm->getConnection()->isTransactionActive()) {
                $errorEm->rollback();
            }

            // Обновляем статус ошибки
            try {
                $errorVideo = $errorEm->find(\App\Entity\Video::class, $message->getVideoId());
                if ($errorVideo) {
                    $errorVideo->setProcessingStatus('error');
                    $errorVideo->setProcessingError($e->getMessage());
                    $errorEm->flush();
                    
                    // Отправляем уведомление об ошибке
                    try {
                        $this->notificationService->notifyVideoFailed($errorVideo);
                    } catch (\Exception $notificationError) {
                        $this->logger->error('Failed to send notification', [
                            'error' => $notificationError->getMessage()
                        ]);
                    }
                }
            } catch (\Exception $dbError) {
                $this->logger->error('Failed to update video error status', [
                    'error' => $dbError->getMessage()
                ]);
            }
            
            throw $e;
        }
    }
}