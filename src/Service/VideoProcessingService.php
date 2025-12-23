<?php

namespace App\Service;

use App\Service\SettingsService;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class VideoProcessingService
{
    private string $ffmpegPath;
    private string $ffprobePath;
    private bool $ffmpegAvailable = false;

    public function __construct(
        private SettingsService $settingsService,
        string $ffmpegPath = 'ffmpeg',
        string $ffprobePath = 'ffprobe'
    ) {
        $this->ffmpegPath = $ffmpegPath;
        $this->ffprobePath = $ffprobePath;
        $this->checkFFmpegAvailability();
    }

    private function checkFFmpegAvailability(): void
    {
        try {
            $process = new Process([$this->ffmpegPath, '-version']);
            $process->run();
            $this->ffmpegAvailable = $process->isSuccessful();
        } catch (\Exception $e) {
            $this->ffmpegAvailable = false;
        }
    }

    public function isFFmpegAvailable(): bool
    {
        return $this->ffmpegAvailable;
    }

    private function ensureFFmpegAvailable(): void
    {
        if (!$this->ffmpegAvailable) {
            throw new \RuntimeException('FFmpeg is not available. Please install FFmpeg and configure the path.');
        }
    }

    /**
     * Get video duration in seconds
     */
    public function getDuration(string $videoPath): int
    {
        $this->ensureFFmpegAvailable();
        $process = new Process([
            $this->ffprobePath,
            '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1',
            $videoPath
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return (int) round((float) $process->getOutput());
    }

    /**
     * Get video resolution
     */
    public function getResolution(string $videoPath): string
    {
        $this->ensureFFmpegAvailable();
        $process = new Process([
            $this->ffprobePath,
            '-v', 'error',
            '-select_streams', 'v:0',
            '-show_entries', 'stream=width,height',
            '-of', 'csv=s=x:p=0',
            $videoPath
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return trim($process->getOutput());
    }

    /**
     * Extract poster image from video middle
     */
    public function extractPoster(string $videoPath, string $outputPath, ?int $atSecond = null): bool
    {
        if ($atSecond === null) {
            $duration = $this->getDuration($videoPath);
            $atSecond = (int) ($duration / 2);
        }

        // Получаем настройки постера
        $posterWidth = $this->settingsService->get('poster_width', 400);
        $posterHeight = $this->settingsService->get('poster_height', 225);
        $posterFormat = strtolower($this->settingsService->get('poster_format', 'JPEG'));
        $posterQuality = $this->settingsService->get('poster_quality', 85);

        // Определяем параметры качества для разных форматов
        $qualityParams = [];
        switch ($posterFormat) {
            case 'jpeg':
                $qualityParams = ['-q:v', (string) (31 - ($posterQuality * 0.31))]; // FFmpeg качество 2-31 (меньше = лучше)
                break;
            case 'png':
                // PNG без потерь, качество не применяется
                break;
            case 'webp':
                $qualityParams = ['-quality', (string) $posterQuality];
                break;
            case 'avif':
                $qualityParams = ['-crf', (string) (51 - ($posterQuality * 0.51))]; // CRF 0-51 (меньше = лучше)
                break;
        }

        $command = [
            $this->ffmpegPath,
            '-i', $videoPath,
            '-ss', (string) $atSecond,
            '-vframes', '1',
            '-vf', "scale={$posterWidth}:{$posterHeight}",
        ];

        // Добавляем параметры качества если они есть
        if (!empty($qualityParams)) {
            $command = array_merge($command, $qualityParams);
        }

        $command[] = '-y';
        $command[] = $outputPath;

        $process = new Process($command);
        $process->setTimeout(60);
        $process->run();

        return $process->isSuccessful() && file_exists($outputPath);
    }

    /**
     * Extract preview clip with segments from entire video
     */
    public function extractPreview(string $videoPath, string $outputPath, ?int $duration = null): bool
    {
        // Получаем настройки превью
        $previewWidth = $this->settingsService->get('preview_width', 640);
        $previewHeight = $this->settingsService->get('preview_height', 360);
        $previewDuration = $duration ?? $this->settingsService->get('preview_duration', 12);
        $previewSegments = $this->settingsService->get('preview_segments', 6);
        $previewFormat = strtolower($this->settingsService->get('preview_format', 'MP4'));
        $previewQuality = $this->settingsService->get('preview_quality', 'medium');

        // Получаем длительность исходного видео
        $videoDuration = $this->getDuration($videoPath);
        
        if ($videoDuration < $previewSegments) {
            // Если видео слишком короткое, просто обрезаем его
            return $this->extractSimplePreview($videoPath, $outputPath, min($previewDuration, $videoDuration));
        }

        // Вычисляем длительность каждого сегмента
        $segmentDuration = $previewDuration / $previewSegments;
        
        // Вычисляем равномерно распределенные точки
        $segmentPoints = [];
        for ($i = 0; $i < $previewSegments; $i++) {
            $point = ($videoDuration / $previewSegments) * $i + ($videoDuration / $previewSegments / 2);
            $segmentPoints[] = max(0, $point - $segmentDuration / 2);
        }

        // Определяем параметры качества
        $qualityParams = $this->getVideoQualityParams($previewQuality);

        // Создаем временные файлы для сегментов
        $tempDir = sys_get_temp_dir();
        $tempFiles = [];
        $concatFile = $tempDir . '/concat_' . uniqid() . '.txt';

        try {
            // Извлекаем каждый сегмент
            foreach ($segmentPoints as $index => $startTime) {
                $tempFile = $tempDir . '/segment_' . $index . '_' . uniqid() . '.mp4';
                $tempFiles[] = $tempFile;

                $command = [
                    $this->ffmpegPath,
                    '-i', $videoPath,
                    '-ss', (string) $startTime,
                    '-t', (string) $segmentDuration,
                    '-vf', "scale={$previewWidth}:{$previewHeight}",
                    '-c:v', 'libx264',
                    '-preset', 'fast',
                ];

                $command = array_merge($command, $qualityParams);
                $command[] = '-an'; // Убираем звук
                $command[] = '-y';
                $command[] = $tempFile;

                $process = new Process($command);
                $process->setTimeout(60);
                $process->run();

                if (!$process->isSuccessful()) {
                    throw new \Exception("Failed to extract segment {$index}: " . $process->getErrorOutput());
                }
            }

            // Создаем файл для конкатенации
            $concatContent = '';
            foreach ($tempFiles as $tempFile) {
                $concatContent .= "file '" . str_replace("'", "'\"'\"'", $tempFile) . "'\n";
            }
            file_put_contents($concatFile, $concatContent);

            // Склеиваем сегменты
            $command = [
                $this->ffmpegPath,
                '-f', 'concat',
                '-safe', '0',
                '-i', $concatFile,
                '-c', 'copy',
                '-y',
                $outputPath
            ];

            $process = new Process($command);
            $process->setTimeout(120);
            $process->run();

            $success = $process->isSuccessful() && file_exists($outputPath);

        } finally {
            // Очищаем временные файлы
            foreach ($tempFiles as $tempFile) {
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
            if (file_exists($concatFile)) {
                unlink($concatFile);
            }
        }

        return $success;
    }

    /**
     * Extract simple preview (fallback for short videos)
     */
    private function extractSimplePreview(string $videoPath, string $outputPath, int $duration): bool
    {
        $previewWidth = $this->settingsService->get('preview_width', 640);
        $previewHeight = $this->settingsService->get('preview_height', 360);
        $previewQuality = $this->settingsService->get('preview_quality', 'medium');

        $qualityParams = $this->getVideoQualityParams($previewQuality);

        $command = [
            $this->ffmpegPath,
            '-i', $videoPath,
            '-t', (string) $duration,
            '-vf', "scale={$previewWidth}:{$previewHeight}",
            '-c:v', 'libx264',
            '-preset', 'fast',
        ];

        $command = array_merge($command, $qualityParams);
        $command[] = '-an'; // Убираем звук
        $command[] = '-y';
        $command[] = $outputPath;

        $process = new Process($command);
        $process->setTimeout(120);
        $process->run();

        return $process->isSuccessful() && file_exists($outputPath);
    }

    /**
     * Encode video to specific profile
     */
    public function encodeToProfile(string $inputPath, string $outputPath, \App\Entity\VideoEncodingProfile $profile): bool
    {
        // Парсим разрешение из профиля (например "1280x720")
        $resolution = $profile->getResolution();
        if (!preg_match('/(\d+)x(\d+)/', $resolution, $matches)) {
            throw new \InvalidArgumentException("Invalid resolution format: {$resolution}");
        }
        
        $width = (int) $matches[1];
        $height = (int) $matches[2];
        $bitrate = $profile->getBitrate();
        $codec = $profile->getCodec();

        $command = [
            $this->ffmpegPath,
            '-i', $inputPath,
            '-vf', "scale={$width}:{$height}",
            '-c:v', $codec,
            '-b:v', "{$bitrate}k",
            '-c:a', 'aac',
            '-b:a', '128k',
            '-preset', 'medium',
            '-movflags', '+faststart',
            '-y',
            $outputPath
        ];

        $process = new Process($command);
        $process->setTimeout(3600); // 1 hour timeout
        $process->run();

        return $process->isSuccessful() && file_exists($outputPath);
    }

    /**
     * Get video quality parameters based on quality setting
     */
    private function getVideoQualityParams(string $quality): array
    {
        return match($quality) {
            'low' => ['-crf', '32', '-maxrate', '500k', '-bufsize', '1000k'],
            'medium' => ['-crf', '28', '-maxrate', '1000k', '-bufsize', '2000k'],
            'high' => ['-crf', '23', '-maxrate', '2000k', '-bufsize', '4000k'],
            default => ['-crf', '28', '-maxrate', '1000k', '-bufsize', '2000k'],
        };
    }

    /**
     * Convert video to specific resolution
     */
    public function convertVideo(
        string $inputPath,
        string $outputPath,
        string $resolution = '720p',
        int $bitrate = 2000
    ): bool {
        $dimensions = $this->getResolutionDimensions($resolution);

        $process = new Process([
            $this->ffmpegPath,
            '-i', $inputPath,
            '-vf', "scale={$dimensions['width']}:{$dimensions['height']}",
            '-c:v', 'libx264',
            '-preset', 'medium',
            '-b:v', "{$bitrate}k",
            '-c:a', 'aac',
            '-b:a', '128k',
            '-movflags', '+faststart',
            '-y',
            $outputPath
        ]);

        $process->setTimeout(3600); // 1 hour timeout
        $process->run();

        return $process->isSuccessful() && file_exists($outputPath);
    }

    /**
     * Process video: extract metadata, poster, preview
     */
    public function processVideo(string $videoPath, string $mediaDir): array
    {
        $result = [
            'success' => false,
            'duration' => 0,
            'resolution' => '',
            'poster' => null,
            'preview' => null,
            'error' => null
        ];

        try {
            // Get duration
            $result['duration'] = $this->getDuration($videoPath);

            // Get resolution
            $result['resolution'] = $this->getResolution($videoPath);

            // Extract poster
            $posterFormat = strtolower($this->settingsService->get('poster_format', 'JPEG'));
            $posterExt = match($posterFormat) {
                'avif' => 'avif',
                'webp' => 'webp',
                'png' => 'png',
                default => 'jpg',
            };
            $posterFilename = 'poster_' . uniqid() . '.' . $posterExt;
            $posterPath = $mediaDir . '/posters/' . $posterFilename;
            
            if (!is_dir(dirname($posterPath))) {
                mkdir(dirname($posterPath), 0777, true);
            }

            if ($this->extractPoster($videoPath, $posterPath)) {
                $result['poster'] = 'posters/' . $posterFilename;
            }

            // Extract preview
            $previewFormat = strtolower($this->settingsService->get('preview_format', 'MP4'));
            $previewExt = match($previewFormat) {
                'webm' => 'webm',
                'avi' => 'avi',
                default => 'mp4',
            };
            $previewFilename = 'preview_' . uniqid() . '.' . $previewExt;
            $previewPath = $mediaDir . '/previews/' . $previewFilename;
            
            if (!is_dir(dirname($previewPath))) {
                mkdir(dirname($previewPath), 0777, true);
            }

            if ($this->extractPreview($videoPath, $previewPath)) {
                $result['preview'] = 'previews/' . $previewFilename;
            }

            $result['success'] = true;
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    private function getResolutionDimensions(string $resolution): array
    {
        return match($resolution) {
            '360p' => ['width' => 640, 'height' => 360],
            '480p' => ['width' => 854, 'height' => 480],
            '720p' => ['width' => 1280, 'height' => 720],
            '1080p' => ['width' => 1920, 'height' => 1080],
            default => ['width' => 1280, 'height' => 720],
        };
    }
}
