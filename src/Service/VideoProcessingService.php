<?php

namespace App\Service;

use App\Service\SettingsService;
use App\Service\ContentProtectionService;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class VideoProcessingService
{
    private string $ffmpegPath;
    private string $ffprobePath;
    private bool $ffmpegAvailable = false;
    private array $ffmpegCapabilities = [];
    private LoggerInterface $logger;

    public function __construct(
        private SettingsService $settingsService,
        private ?ContentProtectionService $contentProtectionService = null,
        ?LoggerInterface $logger = null,
        string $ffmpegPath = 'ffmpeg',
        string $ffprobePath = 'ffprobe'
    ) {
        $this->ffmpegPath = $ffmpegPath;
        $this->ffprobePath = $ffprobePath;
        $this->logger = $logger ?? new NullLogger();
        $this->checkFFmpegAvailability();
        $this->detectFFmpegCapabilities();
    }

    private function checkFFmpegAvailability(): void
    {
        try {
            $process = new Process([$this->ffmpegPath, '-version']);
            $process->setTimeout(10); // 10 секунд таймаут
            $process->run();
            $this->ffmpegAvailable = $process->isSuccessful();
            
            if (!$this->ffmpegAvailable) {
                error_log("FFmpeg not available: " . $process->getErrorOutput());
            }
        } catch (\Exception $e) {
            $this->ffmpegAvailable = false;
            error_log("FFmpeg check failed: " . $e->getMessage());
        }
    }

    /**
     * Определяет доступные возможности FFmpeg
     */
    private function detectFFmpegCapabilities(): void
    {
        if (!$this->ffmpegAvailable) {
            return;
        }

        try {
            // Проверяем доступные кодеки
            $process = new Process([$this->ffmpegPath, '-codecs']);
            $process->setTimeout(10);
            $process->run();
            
            if ($process->isSuccessful()) {
                $output = $process->getOutput();
                $this->ffmpegCapabilities['h264'] = strpos($output, 'libx264') !== false;
                $this->ffmpegCapabilities['h265'] = strpos($output, 'libx265') !== false;
                $this->ffmpegCapabilities['vp9'] = strpos($output, 'libvpx-vp9') !== false;
                $this->ffmpegCapabilities['av1'] = strpos($output, 'libaom-av1') !== false;
            }

            // Проверяем доступные форматы
            $process = new Process([$this->ffmpegPath, '-formats']);
            $process->setTimeout(10);
            $process->run();
            
            if ($process->isSuccessful()) {
                $output = $process->getOutput();
                $this->ffmpegCapabilities['mp4'] = strpos($output, 'mp4') !== false;
                $this->ffmpegCapabilities['webm'] = strpos($output, 'webm') !== false;
                $this->ffmpegCapabilities['avi'] = strpos($output, 'avi') !== false;
            }
        } catch (\Exception $e) {
            error_log("FFmpeg capabilities detection failed: " . $e->getMessage());
        }
    }

    public function isFFmpegAvailable(): bool
    {
        return $this->ffmpegAvailable;
    }

    /**
     * Проверяет поддержку конкретного кодека или формата
     */
    public function supportsCodec(string $codec): bool
    {
        return $this->ffmpegCapabilities[$codec] ?? false;
    }

    /**
     * Получает информацию о возможностях FFmpeg
     */
    public function getCapabilities(): array
    {
        return $this->ffmpegCapabilities;
    }

    /**
     * Проверяет системные требования для обработки видео
     */
    public function checkSystemRequirements(): array
    {
        $requirements = [
            'ffmpeg_available' => $this->ffmpegAvailable,
            'disk_space' => $this->checkDiskSpace(),
            'memory_limit' => $this->checkMemoryLimit(),
            'temp_dir_writable' => $this->checkTempDirectory(),
        ];

        return $requirements;
    }

    private function checkDiskSpace(): bool
    {
        $tempDir = sys_get_temp_dir();
        $freeBytes = disk_free_space($tempDir);
        $requiredBytes = 1024 * 1024 * 1024; // 1GB минимум
        
        return $freeBytes !== false && $freeBytes > $requiredBytes;
    }

    private function checkMemoryLimit(): bool
    {
        $memoryLimit = ini_get('memory_limit');
        if ($memoryLimit === '-1') {
            return true; // Без ограничений
        }
        
        $bytes = $this->convertToBytes($memoryLimit);
        $requiredBytes = 256 * 1024 * 1024; // 256MB минимум
        
        return $bytes >= $requiredBytes;
    }

    private function checkTempDirectory(): bool
    {
        $tempDir = sys_get_temp_dir();
        return is_dir($tempDir) && is_writable($tempDir);
    }

    private function convertToBytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;

        return match($last) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }

    private function ensureFFmpegAvailable(): void
    {
        if (!$this->ffmpegAvailable) {
            throw new \RuntimeException(
                'FFmpeg is not available. Please install FFmpeg and configure the path. ' .
                'Current path: ' . $this->ffmpegPath
            );
        }
    }

    /**
     * Безопасное выполнение FFmpeg команды с обработкой ошибок
     */
    private function executeFFmpegCommand(array $command, int $timeout = 300): array
    {
        $this->ensureFFmpegAvailable();

        $process = new Process($command);
        $process->setTimeout($timeout);
        
        try {
            $process->run();
            
            return [
                'success' => $process->isSuccessful(),
                'output' => $process->getOutput(),
                'error' => $process->getErrorOutput(),
                'exit_code' => $process->getExitCode(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'output' => '',
                'error' => $e->getMessage(),
                'exit_code' => -1,
            ];
        }
    }

    /**
     * Валидация входного видеофайла
     */
    private function validateVideoFile(string $videoPath): void
    {
        if (!file_exists($videoPath)) {
            throw new \InvalidArgumentException("Video file does not exist: {$videoPath}");
        }

        if (!is_readable($videoPath)) {
            throw new \InvalidArgumentException("Video file is not readable: {$videoPath}");
        }

        $fileSize = filesize($videoPath);
        if ($fileSize === false || $fileSize === 0) {
            throw new \InvalidArgumentException("Video file is empty or unreadable: {$videoPath}");
        }

        // Проверяем максимальный размер файла (2GB)
        $maxSize = 2 * 1024 * 1024 * 1024;
        if ($fileSize > $maxSize) {
            throw new \InvalidArgumentException("Video file is too large: {$fileSize} bytes (max: {$maxSize})");
        }
    }

    /**
     * Get video duration in seconds
     */
    public function getDuration(string $videoPath): int
    {
        $this->validateVideoFile($videoPath);

        $command = [
            $this->ffprobePath,
            '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1',
            $videoPath
        ];

        $result = $this->executeFFmpegCommand($command, 30);

        if (!$result['success']) {
            throw new \RuntimeException(
                "Failed to get video duration: " . $result['error']
            );
        }

        $duration = trim($result['output']);
        if (!is_numeric($duration)) {
            throw new \RuntimeException("Invalid duration format: {$duration}");
        }

        return (int) round((float) $duration);
    }

    /**
     * Get video resolution
     */
    public function getResolution(string $videoPath): string
    {
        $this->validateVideoFile($videoPath);

        $command = [
            $this->ffprobePath,
            '-v', 'error',
            '-select_streams', 'v:0',
            '-show_entries', 'stream=width,height',
            '-of', 'csv=s=x:p=0',
            $videoPath
        ];

        $result = $this->executeFFmpegCommand($command, 30);

        if (!$result['success']) {
            throw new \RuntimeException(
                "Failed to get video resolution: " . $result['error']
            );
        }

        $resolution = trim($result['output']);
        if (!preg_match('/^\d+x\d+$/', $resolution)) {
            throw new \RuntimeException("Invalid resolution format: {$resolution}");
        }

        return $resolution;
    }

    /**
     * Получить детальную информацию о видео
     */
    public function getVideoInfo(string $videoPath): array
    {
        $this->validateVideoFile($videoPath);

        $command = [
            $this->ffprobePath,
            '-v', 'quiet',
            '-print_format', 'json',
            '-show_format',
            '-show_streams',
            $videoPath
        ];

        $result = $this->executeFFmpegCommand($command, 30);

        if (!$result['success']) {
            throw new \RuntimeException(
                "Failed to get video info: " . $result['error']
            );
        }

        $info = json_decode($result['output'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Invalid JSON response from ffprobe");
        }

        return $info;
    }

    /**
     * Extract poster image from video middle
     */
    public function extractPoster(string $videoPath, string $outputPath, ?int $atSecond = null): bool
    {
        $this->validateVideoFile($videoPath);

        try {
            if ($atSecond === null) {
                $duration = $this->getDuration($videoPath);
                $atSecond = max(1, (int) ($duration / 2)); // Минимум 1 секунда
            }

            // Получаем настройки постера
            $posterWidth = $this->settingsService->get('poster_width', 400);
            $posterHeight = $this->settingsService->get('poster_height', 225);
            $posterFormat = strtolower($this->settingsService->get('poster_format', 'JPEG'));
            $posterQuality = $this->settingsService->get('poster_quality', 85);

            // Создаем директорию если нужно
            $outputDir = dirname($outputPath);
            if (!is_dir($outputDir)) {
                if (!mkdir($outputDir, 0755, true)) {
                    throw new \RuntimeException("Cannot create output directory: {$outputDir}");
                }
            }

            // Определяем параметры качества для разных форматов
            $qualityParams = [];
            $codecParams = [];
            switch ($posterFormat) {
                case 'jpeg':
                case 'jpg':
                    $qualityParams = ['-q:v', (string) max(2, 31 - ($posterQuality * 0.29))];
                    break;
                case 'png':
                    // PNG без потерь, качество не применяется
                    break;
                case 'webp':
                    $codecParams = ['-c:v', 'libwebp'];
                    $qualityParams = ['-quality', (string) $posterQuality];
                    break;
                case 'avif':
                    $codecParams = ['-c:v', 'libaom-av1', '-still-picture', '1'];
                    $qualityParams = ['-crf', (string) max(0, 63 - ($posterQuality * 0.63))];
                    break;
            }

            $command = [
                $this->ffmpegPath,
                '-i', $videoPath,
                '-ss', (string) $atSecond,
                '-vframes', '1',
                '-vf', "scale={$posterWidth}:{$posterHeight}:force_original_aspect_ratio=decrease,pad={$posterWidth}:{$posterHeight}:(ow-iw)/2:(oh-ih)/2",
                '-avoid_negative_ts', 'make_zero',
            ];

            // Добавляем кодек если нужен (для AVIF, WebP)
            if (!empty($codecParams)) {
                $command = array_merge($command, $codecParams);
            }

            // Добавляем параметры качества если они есть
            if (!empty($qualityParams)) {
                $command = array_merge($command, $qualityParams);
            }

            $command[] = '-y';
            $command[] = $outputPath;

            $result = $this->executeFFmpegCommand($command, 60);

            if (!$result['success']) {
                error_log("Poster extraction failed: " . $result['error']);
                return false;
            }

            // Проверяем что файл создался и имеет разумный размер
            if (!file_exists($outputPath)) {
                error_log("Poster file was not created: {$outputPath}");
                return false;
            }

            $fileSize = filesize($outputPath);
            if ($fileSize === false || $fileSize < 100) { // Минимум 100 байт
                error_log("Poster file is too small: {$fileSize} bytes");
                unlink($outputPath);
                return false;
            }

            return true;

        } catch (\Exception $e) {
            error_log("Poster extraction exception: " . $e->getMessage());
            if (file_exists($outputPath)) {
                unlink($outputPath);
            }
            return false;
        }
    }

    /**
     * Extract preview clip with segments from entire video (optimized)
     */
    public function extractPreview(string $videoPath, string $outputPath, ?int $duration = null): bool
    {
        $this->validateVideoFile($videoPath);

        // Получаем настройки превью
        $previewWidth = $this->settingsService->get('preview_width', 640);
        $previewHeight = $this->settingsService->get('preview_height', 360);
        $previewDuration = $duration ?? $this->settingsService->get('preview_duration', 12);
        $previewSegments = $this->settingsService->get('preview_segments', 6);
        $previewQuality = $this->settingsService->get('preview_quality', 'medium');

        try {
            // Получаем длительность исходного видео
            $videoDuration = $this->getDuration($videoPath);
            
            if ($videoDuration < $previewSegments) {
                // Если видео слишком короткое, просто обрезаем его
                return $this->extractSimplePreview($videoPath, $outputPath, min($previewDuration, $videoDuration));
            }

            // Создаем директорию если нужно
            $outputDir = dirname($outputPath);
            if (!is_dir($outputDir)) {
                if (!mkdir($outputDir, 0755, true)) {
                    throw new \RuntimeException("Cannot create output directory: {$outputDir}");
                }
            }

            // Вычисляем длительность каждого сегмента
            $segmentDuration = $previewDuration / $previewSegments;
            
            // Вычисляем равномерно распределенные точки
            $segmentPoints = [];
            for ($i = 0; $i < $previewSegments; $i++) {
                $point = ($videoDuration / $previewSegments) * $i + ($videoDuration / $previewSegments / 2);
                $segmentPoints[] = max(0, $point - $segmentDuration / 2);
            }

            // Определяем параметры качества с аппаратным ускорением
            $qualityParams = $this->getVideoQualityParams($previewQuality);

            // Создаем команду для извлечения превью
            $command = [$this->ffmpegPath];
            
            // Добавляем входные файлы
            for ($i = 0; $i < $previewSegments; $i++) {
                $startTime = $segmentPoints[$i];
                array_push($command, '-ss', (string) $startTime, '-t', (string) $segmentDuration, '-i', $videoPath);
            }
            
            // Создаем фильтры для масштабирования
            $filterMaps = [];
            for ($i = 0; $i < $previewSegments; $i++) {
                $filterMaps[] = "[{$i}:v]scale={$previewWidth}:{$previewHeight}[v{$i}]";
            }
            
            // Объединяем все сегменты
            $concatFilter = implode('', array_map(fn($i) => "[v{$i}]", range(0, $previewSegments - 1))) . 
                           "concat=n={$previewSegments}:v=1:a=0[outv]";
            
            array_push($command, '-filter_complex', implode(';', array_merge($filterMaps, [$concatFilter])));
            array_push($command, '-map', '[outv]');
            
            // Добавляем параметры качества
            foreach ($qualityParams as $param) {
                $command[] = $param;
            }
            
            array_push($command, '-an', '-y', $outputPath);

            $result = $this->executeFFmpegCommand($command, 300);

            if (!$result['success']) {
                $this->logger->error("Preview extraction failed", [
                    'error' => $result['error'],
                    'command' => implode(' ', $command)
                ]);
                return false;
            }

            // Проверяем что файл создался и имеет разумный размер
            if (!file_exists($outputPath)) {
                $this->logger->error("Preview file was not created: {$outputPath}");
                return false;
            }

            $fileSize = filesize($outputPath);
            if ($fileSize === false || $fileSize < 1000) { // Минимум 1KB
                $this->logger->error("Preview file is too small: {$fileSize} bytes");
                unlink($outputPath);
                return false;
            }

            return true;

        } catch (\Exception $e) {
            $this->logger->error("Preview extraction exception", [
                'error' => $e->getMessage(),
                'video' => $videoPath
            ]);
            if (file_exists($outputPath)) {
                unlink($outputPath);
            }
            return false;
        }
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
        // Парсим разрешение из профиля
        $resolution = $profile->getResolution();
        
        // Поддерживаем оба формата: "1280x720" и "720p"
        if (preg_match('/(\d+)x(\d+)/', $resolution, $matches)) {
            $width = (int) $matches[1];
            $height = (int) $matches[2];
        } elseif (preg_match('/(\d+)p/', $resolution, $matches)) {
            // Конвертируем формат "720p" в размеры
            $height = (int) $matches[1];
            $width = match($height) {
                360 => 640,
                480 => 854,
                720 => 1280,
                1080 => 1920,
                1440 => 2560,
                2160 => 3840,
                default => (int) round($height * 16 / 9)
            };
        } else {
            $this->logger->error("Invalid resolution format", ['resolution' => $resolution]);
            throw new \InvalidArgumentException("Invalid resolution format: {$resolution}");
        }
        
        $bitrate = $profile->getBitrate() ?: match($height) {
            360 => 800,
            480 => 1500,
            720 => 2500,
            1080 => 5000,
            1440 => 8000,
            2160 => 15000,
            default => 2500
        };
        
        $codec = $profile->getCodec() ?: 'libx264';
        
        // Маппинг кодеков: h264 -> libx264, h265 -> libx265 и т.д.
        $codec = match(strtolower($codec)) {
            'h264', 'x264', 'avc' => 'libx264',
            'h265', 'x265', 'hevc' => 'libx265',
            'vp9' => 'libvpx-vp9',
            'av1' => 'libaom-av1',
            default => $codec,
        };

        // Получаем формат из профиля
        $format = strtolower($profile->getFormat() ?: 'mp4');
        
        // Определяем расширение файла и параметры контейнера
        $containerParams = $this->getContainerParams($format);
        
        // Обновляем путь выходного файла с правильным расширением
        $outputPath = $this->updateOutputPathExtension($outputPath, $format);

        $this->logger->info('Encoding video', [
            'input' => $inputPath,
            'output' => $outputPath,
            'resolution' => "{$width}x{$height}",
            'bitrate' => $bitrate,
            'codec' => $codec,
            'format' => $format
        ]);

        $command = [
            $this->ffmpegPath,
            '-i', $inputPath,
            '-vf', "scale={$width}:{$height}:force_original_aspect_ratio=decrease,pad={$width}:{$height}:(ow-iw)/2:(oh-ih)/2",
            '-c:v', $codec,
            '-b:v', "{$bitrate}k",
        ];

        // Добавляем аудио кодек в зависимости от формата
        if ($format === 'mkv' && $codec === 'libx265') {
            // Для MKV с H.265 можем использовать более качественный аудио
            $command = array_merge($command, ['-c:a', 'flac']);
        } elseif ($format === 'avi') {
            // Для AVI используем MP3 для лучшей совместимости
            $command = array_merge($command, ['-c:a', 'mp3', '-b:a', '192k']);
        } else {
            // Для MP4, MOV используем AAC
            $command = array_merge($command, ['-c:a', 'aac', '-b:a', '128k']);
        }

        // Добавляем параметры пресета
        $command = array_merge($command, ['-preset', 'medium']);
        
        // Добавляем специфичные для контейнера параметры
        $command = array_merge($command, $containerParams);
        
        // Добавляем выходной файл
        $command = array_merge($command, ['-y', $outputPath]);

        $process = new Process($command);
        $process->setTimeout(3600); // 1 hour timeout
        
        $this->logger->info('Running FFmpeg command', ['command' => implode(' ', $command)]);
        
        $process->run();

        if (!$process->isSuccessful()) {
            $this->logger->error('FFmpeg encoding failed', [
                'exitCode' => $process->getExitCode(),
                'error' => $process->getErrorOutput()
            ]);
            return false;
        }

        $success = file_exists($outputPath) && filesize($outputPath) > 0;
        
        $this->logger->info('Encoding result', [
            'success' => $success,
            'outputExists' => file_exists($outputPath),
            'outputSize' => file_exists($outputPath) ? filesize($outputPath) : 0
        ]);

        return $success;
    }

    /**
     * Получает параметры контейнера в зависимости от формата
     */
    private function getContainerParams(string $format): array
    {
        return match($format) {
            'mp4' => ['-movflags', '+faststart'],
            'mov' => ['-movflags', '+faststart'],
            'mkv' => ['-f', 'matroska'],
            'avi' => ['-f', 'avi'],
            default => ['-movflags', '+faststart'],
        };
    }

    /**
     * Обновляет расширение файла в соответствии с форматом
     */
    private function updateOutputPathExtension(string $outputPath, string $format): string
    {
        $pathInfo = pathinfo($outputPath);
        $extension = match($format) {
            'mp4' => 'mp4',
            'mov' => 'mov',
            'mkv' => 'mkv',
            'avi' => 'avi',
            default => 'mp4',
        };
        
        return $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.' . $extension;
    }

    /**
     * Get video quality parameters based on quality setting with hardware acceleration
     */
    private function getVideoQualityParams(string $quality): array
    {
        // Проверяем доступность аппаратного ускорения
        $hwAccel = $this->getHardwareAcceleration();
        
        if (!empty($hwAccel)) {
            // Используем аппаратное ускорение
            return match($quality) {
                'low' => array_merge($hwAccel, ['-b:v', '500k', '-maxrate', '750k', '-bufsize', '1000k']),
                'medium' => array_merge($hwAccel, ['-b:v', '1000k', '-maxrate', '1500k', '-bufsize', '2000k']),
                'high' => array_merge($hwAccel, ['-b:v', '2000k', '-maxrate', '3000k', '-bufsize', '4000k']),
                default => array_merge($hwAccel, ['-b:v', '1000k', '-maxrate', '1500k', '-bufsize', '2000k']),
            };
        }
        
        // Fallback на программное кодирование
        return match($quality) {
            'low' => ['-c:v', 'libx264', '-crf', '32', '-preset', 'fast', '-maxrate', '500k', '-bufsize', '1000k'],
            'medium' => ['-c:v', 'libx264', '-crf', '28', '-preset', 'medium', '-maxrate', '1000k', '-bufsize', '2000k'],
            'high' => ['-c:v', 'libx264', '-crf', '23', '-preset', 'slow', '-maxrate', '2000k', '-bufsize', '4000k'],
            default => ['-c:v', 'libx264', '-crf', '28', '-preset', 'medium', '-maxrate', '1000k', '-bufsize', '2000k'],
        };
    }

    /**
     * Определяет доступное аппаратное ускорение
     */
    private function getHardwareAcceleration(): array
    {
        // NVIDIA NVENC
        if ($this->supportsCodec('h264_nvenc')) {
            return ['-c:v', 'h264_nvenc', '-preset', 'fast'];
        }
        
        // Intel QuickSync
        if ($this->supportsCodec('h264_qsv')) {
            return ['-c:v', 'h264_qsv', '-preset', 'medium'];
        }
        
        // AMD VCE
        if ($this->supportsCodec('h264_amf')) {
            return ['-c:v', 'h264_amf', '-quality', 'balanced'];
        }
        
        // Apple VideoToolbox (macOS)
        if ($this->supportsCodec('h264_videotoolbox')) {
            return ['-c:v', 'h264_videotoolbox', '-b:v', '1000k'];
        }
        
        return []; // Нет аппаратного ускорения
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

            $this->logger->info('Attempting to extract preview', [
                'videoPath' => $videoPath,
                'previewPath' => $previewPath,
                'previewFormat' => $previewFormat
            ]);

            $previewResult = $this->extractPreview($videoPath, $previewPath);
            
            $this->logger->info('Preview extraction result', [
                'success' => $previewResult,
                'fileExists' => file_exists($previewPath),
                'fileSize' => file_exists($previewPath) ? filesize($previewPath) : 0
            ]);

            if ($previewResult) {
                $result['preview'] = 'previews/' . $previewFilename;
            } else {
                $this->logger->warning('Preview extraction failed for video', [
                    'videoPath' => $videoPath
                ]);
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

    /**
     * Добавляет водяной знак на видео
     */
    public function addWatermark(string $inputPath, string $outputPath, ?array $options = null): bool
    {
        $this->validateVideoFile($inputPath);

        // Получаем настройки водяного знака
        if ($options === null && $this->contentProtectionService !== null) {
            if (!$this->contentProtectionService->isWatermarkEnabled()) {
                // Если водяной знак отключен, просто копируем файл
                return copy($inputPath, $outputPath);
            }
            
            $options = [
                'text' => $this->contentProtectionService->getWatermarkText(),
                'position' => $this->contentProtectionService->getWatermarkPosition(),
                'opacity' => $this->contentProtectionService->getWatermarkOpacity(),
            ];
        }

        if (empty($options['text'])) {
            // Если текст пустой, копируем файл без изменений
            return copy($inputPath, $outputPath);
        }

        // Определяем позицию водяного знака
        $position = $this->getWatermarkPosition($options['position'] ?? 'bottom-right');
        
        // Вычисляем прозрачность (0-1)
        $opacity = ($options['opacity'] ?? 50) / 100;
        
        // Экранируем текст для FFmpeg
        $text = $this->escapeFFmpegText($options['text']);
        
        // Размер шрифта (можно сделать настраиваемым)
        $fontSize = $options['font_size'] ?? 24;
        
        // Цвет текста с прозрачностью
        $fontColor = sprintf('white@%.2f', $opacity);
        
        // Создаем фильтр drawtext
        $drawtext = sprintf(
            "drawtext=text='%s':fontsize=%d:fontcolor=%s:%s:shadowcolor=black@%.2f:shadowx=2:shadowy=2",
            $text,
            $fontSize,
            $fontColor,
            $position,
            $opacity * 0.5
        );

        $command = [
            $this->ffmpegPath,
            '-i', $inputPath,
            '-vf', $drawtext,
            '-c:v', 'libx264',
            '-preset', 'medium',
            '-crf', '23',
            '-c:a', 'copy',
            '-movflags', '+faststart',
            '-y',
            $outputPath
        ];

        $result = $this->executeFFmpegCommand($command, 3600);

        if (!$result['success']) {
            error_log("Watermark failed: " . $result['error']);
            return false;
        }

        return file_exists($outputPath) && filesize($outputPath) > 0;
    }

    /**
     * Добавляет изображение-водяной знак на видео
     */
    public function addImageWatermark(string $inputPath, string $outputPath, string $watermarkImage, ?array $options = null): bool
    {
        $this->validateVideoFile($inputPath);

        if (!file_exists($watermarkImage)) {
            throw new \InvalidArgumentException("Watermark image does not exist: {$watermarkImage}");
        }

        $position = $options['position'] ?? 'bottom-right';
        $opacity = ($options['opacity'] ?? 50) / 100;
        $scale = $options['scale'] ?? 0.15; // 15% от ширины видео

        // Определяем позицию overlay
        $overlayPosition = match($position) {
            'top-left' => 'overlay=10:10',
            'top-right' => 'overlay=W-w-10:10',
            'bottom-left' => 'overlay=10:H-h-10',
            'bottom-right' => 'overlay=W-w-10:H-h-10',
            'center' => 'overlay=(W-w)/2:(H-h)/2',
            default => 'overlay=W-w-10:H-h-10',
        };

        // Создаем сложный фильтр
        $filterComplex = sprintf(
            "[1:v]scale=iw*%.2f:-1,format=rgba,colorchannelmixer=aa=%.2f[wm];[0:v][wm]%s",
            $scale,
            $opacity,
            $overlayPosition
        );

        $command = [
            $this->ffmpegPath,
            '-i', $inputPath,
            '-i', $watermarkImage,
            '-filter_complex', $filterComplex,
            '-c:v', 'libx264',
            '-preset', 'medium',
            '-crf', '23',
            '-c:a', 'copy',
            '-movflags', '+faststart',
            '-y',
            $outputPath
        ];

        $result = $this->executeFFmpegCommand($command, 3600);

        return $result['success'] && file_exists($outputPath) && filesize($outputPath) > 0;
    }

    /**
     * Получает позицию водяного знака для FFmpeg drawtext
     */
    private function getWatermarkPosition(string $position): string
    {
        return match($position) {
            'top-left' => 'x=10:y=10',
            'top-right' => 'x=w-tw-10:y=10',
            'bottom-left' => 'x=10:y=h-th-10',
            'bottom-right' => 'x=w-tw-10:y=h-th-10',
            'center' => 'x=(w-tw)/2:y=(h-th)/2',
            default => 'x=w-tw-10:y=h-th-10',
        };
    }

    /**
     * Экранирует текст для использования в FFmpeg drawtext
     */
    private function escapeFFmpegText(string $text): string
    {
        // Экранируем специальные символы для FFmpeg
        $text = str_replace(['\\', "'", ':', '%'], ['\\\\', "\\'", '\\:', '\\%'], $text);
        return $text;
    }

    /**
     * Проверяет, включен ли водяной знак
     */
    public function isWatermarkEnabled(): bool
    {
        if ($this->contentProtectionService === null) {
            return false;
        }
        return $this->contentProtectionService->isWatermarkEnabled();
    }

    /**
     * Кодирует видео с водяным знаком если он включен
     */
    public function encodeWithWatermark(string $inputPath, string $outputPath, \App\Entity\VideoEncodingProfile $profile): bool
    {
        // Если водяной знак включен, сначала добавляем его
        if ($this->isWatermarkEnabled()) {
            $tempPath = sys_get_temp_dir() . '/watermarked_' . uniqid() . '.mp4';
            
            try {
                if (!$this->addWatermark($inputPath, $tempPath)) {
                    // Если не удалось добавить водяной знак, продолжаем без него
                    error_log("Failed to add watermark, encoding without it");
                    return $this->encodeToProfile($inputPath, $outputPath, $profile);
                }
                
                $result = $this->encodeToProfile($tempPath, $outputPath, $profile);
                
                return $result;
            } finally {
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }
            }
        }

        return $this->encodeToProfile($inputPath, $outputPath, $profile);
    }
}
