<?php

namespace App\MessageHandler;

use App\Message\GeneratePreviewMessage;
use App\Repository\VideoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Process;

/**
 * Handler for generating animated video previews.
 * 
 * @see Requirements 13.3 - Generate animated preview (WebP/GIF)
 */
#[AsMessageHandler]
class GeneratePreviewMessageHandler
{
    public function __construct(
        private VideoRepository $videoRepository,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private string $projectDir,
    ) {
    }

    public function __invoke(GeneratePreviewMessage $message): void
    {
        $video = $this->videoRepository->find($message->getVideoId());
        
        if ($video === null) {
            $this->logger->warning('Video not found for preview generation', [
                'videoId' => $message->getVideoId(),
            ]);
            return;
        }

        $preview = $video->getPreview();
        if (empty($preview)) {
            $this->logger->warning('Video has no preview file', [
                'videoId' => $video->getId(),
            ]);
            return;
        }

        $previewPath = $this->projectDir . '/public/media/previews/' . $preview;
        if (!file_exists($previewPath)) {
            $this->logger->warning('Preview file not found', [
                'videoId' => $video->getId(),
                'path' => $previewPath,
            ]);
            return;
        }

        try {
            $animatedPreviewName = $this->generateAnimatedPreview($previewPath, $video->getId());
            
            if ($animatedPreviewName !== null) {
                $video->setAnimatedPreview($animatedPreviewName);
                $this->em->flush();
                
                $this->logger->info('Animated preview generated', [
                    'videoId' => $video->getId(),
                    'preview' => $animatedPreviewName,
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate animated preview', [
                'videoId' => $video->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function generateAnimatedPreview(string $sourcePath, int $videoId): ?string
    {
        $outputDir = $this->projectDir . '/public/media/previews';
        $outputName = 'animated_' . uniqid() . '_' . $videoId . '.webp';
        $outputPath = $outputDir . '/' . $outputName;

        // Generate animated WebP from video preview using FFmpeg
        // Extract 3 seconds starting from 1 second, scale to 320px width
        $process = new Process([
            'ffmpeg',
            '-i', $sourcePath,
            '-ss', '1',
            '-t', '3',
            '-vf', 'scale=320:-1:flags=lanczos,fps=10',
            '-loop', '0',
            '-y',
            $outputPath,
        ]);

        $process->setTimeout(60);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->logger->warning('FFmpeg failed, trying GIF fallback', [
                'error' => $process->getErrorOutput(),
            ]);
            
            // Fallback to GIF
            return $this->generateGifPreview($sourcePath, $videoId);
        }

        return $outputName;
    }

    private function generateGifPreview(string $sourcePath, int $videoId): ?string
    {
        $outputDir = $this->projectDir . '/public/media/previews';
        $outputName = 'animated_' . uniqid() . '_' . $videoId . '.gif';
        $outputPath = $outputDir . '/' . $outputName;

        $process = new Process([
            'ffmpeg',
            '-i', $sourcePath,
            '-ss', '1',
            '-t', '3',
            '-vf', 'scale=320:-1:flags=lanczos,fps=8',
            '-y',
            $outputPath,
        ]);

        $process->setTimeout(60);
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        return $outputName;
    }
}
