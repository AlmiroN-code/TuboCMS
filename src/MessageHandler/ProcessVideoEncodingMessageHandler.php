<?php

namespace App\MessageHandler;

use App\Entity\VideoFile;
use App\Message\ProcessVideoEncodingMessage;
use App\Repository\VideoRepository;
use App\Repository\VideoEncodingProfileRepository;
use App\Service\VideoProcessingService;
use App\Service\NotificationService;
use App\Service\StorageManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\LockFactory;

#[AsMessageHandler]
class ProcessVideoEncodingMessageHandler
{
    public function __construct(
        private VideoRepository $videoRepository,
        private VideoEncodingProfileRepository $profileRepository,
        private VideoProcessingService $videoProcessor,
        private NotificationService $notificationService,
        private StorageManager $storageManager,
        private ManagerRegistry $doctrine,
        private LoggerInterface $logger,
        private Filesystem $filesystem,
        private LockFactory $lockFactory,
        private string $projectDir
    ) {
    }

    public function __invoke(ProcessVideoEncodingMessage $message): void
    {
        // Создаём блокировку для предотвращения двойной обработки
        $lock = $this->lockFactory->createLock('video_encoding_' . $message->getVideoId(), 3600);
        
        if (!$lock->acquire()) {
            $this->logger->warning('Video is already being processed', ['videoId' => $message->getVideoId()]);
            return;
        }

        try {
            $this->processVideo($message);
        } finally {
            $lock->release();
        }
    }

    private function processVideo(ProcessVideoEncodingMessage $message): void
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
            
            // Получаем default storage для загрузки
            $defaultStorage = $this->storageManager->getDefaultStorage();
            
            if ($basicResult['success']) {
                $video->setDuration($basicResult['duration']);
                $video->setResolution($basicResult['resolution']);
                
                // Постер сохраняется локально
                if ($basicResult['poster']) {
                    $video->setPoster($basicResult['poster']);
                }
                
                // Превью сохраняется локально
                if ($basicResult['preview']) {
                    $video->setPreview($basicResult['preview']);
                }
            }

            $video->setProcessingProgress(20);
            $em->flush();

            // Получаем разрешение исходного видео для умного кодирования
            $sourceResolution = $basicResult['resolution'] ?? $video->getResolution();
            $sourceHeight = $this->parseResolutionHeight($sourceResolution);
            
            $this->logger->info('Source video resolution', [
                'resolution' => $sourceResolution,
                'height' => $sourceHeight
            ]);

            // Фильтруем профили — не кодируем в качество выше исходного
            $applicableProfiles = array_filter($profiles, function($profile) use ($sourceHeight) {
                $profileHeight = $this->parseResolutionHeight($profile->getResolution());
                return $profileHeight <= $sourceHeight;
            });

            if (empty($applicableProfiles)) {
                // Если нет подходящих профилей, берём самый низкий
                $applicableProfiles = [reset($profiles)];
                $this->logger->warning('No applicable profiles for source resolution, using lowest', [
                    'sourceHeight' => $sourceHeight,
                    'fallbackProfile' => reset($profiles)->getName()
                ]);
            }

            $this->logger->info('Applicable encoding profiles after smart filtering', [
                'total' => count($profiles),
                'applicable' => count($applicableProfiles),
                'profiles' => array_map(fn($p) => $p->getName(), $applicableProfiles)
            ]);

            // Кодируем видео в разные качества
            $totalProfiles = count($applicableProfiles);
            $processedProfiles = 0;
            $isPrimarySet = false;

            foreach ($applicableProfiles as $profile) {
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
                        
                        // Загружаем на удалённое хранилище если настроено
                        if ($defaultStorage !== null) {
                            $remotePath = sprintf(
                                'videos/%d/%s/%s',
                                $video->getId(),
                                strtolower($profile->getName()),
                                $outputFilename
                            );
                            
                            $uploadResult = $this->storageManager->uploadFile($outputPath, $remotePath, $defaultStorage);
                            
                            if ($uploadResult->success) {
                                $videoFile->setStorage($defaultStorage);
                                $videoFile->setRemotePath($uploadResult->remotePath);
                                
                                $this->logger->info('Encoded video uploaded to remote storage', [
                                    'videoId' => $video->getId(),
                                    'profile' => $profile->getName(),
                                    'remotePath' => $uploadResult->remotePath
                                ]);
                            } else {
                                $this->logger->warning('Failed to upload encoded video to remote storage, keeping local', [
                                    'videoId' => $video->getId(),
                                    'profile' => $profile->getName(),
                                    'error' => $uploadResult->errorMessage
                                ]);
                            }
                        }
                        
                        // Первый профиль помечаем как основной
                        if (!$isPrimarySet) {
                            $videoFile->setPrimary(true);
                            $isPrimarySet = true;
                        }

                        $em->persist($videoFile);
                        $processedProfiles++;

                        $this->logger->info('Profile encoded successfully', [
                            'profile' => $profile->getName(),
                            'fileSize' => filesize($outputPath),
                            'isRemote' => $videoFile->isRemote()
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

    /**
     * Парсит высоту из строки разрешения (например "1920x1080" -> 1080)
     */
    private function parseResolutionHeight(?string $resolution): int
    {
        if (!$resolution) {
            return 0;
        }

        // Формат "WIDTHxHEIGHT" (например "1920x1080")
        if (preg_match('/(\d+)x(\d+)/i', $resolution, $matches)) {
            return (int) $matches[2];
        }

        // Формат "720p", "1080p" и т.д.
        if (preg_match('/(\d+)p/i', $resolution, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }
}