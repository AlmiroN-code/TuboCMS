<?php

namespace App\Command;

use App\Entity\VideoEncodingProfile;
use App\Service\VideoProcessingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-encoding-formats',
    description: 'Тестирует кодирование видео в различные форматы'
)]
class TestEncodingFormatsCommand extends Command
{
    public function __construct(
        private VideoProcessingService $videoProcessor,
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('video-file', 'f', InputOption::VALUE_REQUIRED, 'Путь к тестовому видеофайлу')
            ->addOption('profile-id', 'p', InputOption::VALUE_REQUIRED, 'ID профиля кодирования для тестирования');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Проверяем доступность FFmpeg
        if (!$this->videoProcessor->isFFmpegAvailable()) {
            $io->error('FFmpeg недоступен. Установите FFmpeg для тестирования.');
            return Command::FAILURE;
        }

        $io->success('FFmpeg доступен');

        // Показываем возможности FFmpeg
        $capabilities = $this->videoProcessor->getCapabilities();
        $io->section('Возможности FFmpeg:');
        foreach ($capabilities as $feature => $supported) {
            $status = $supported ? '✓' : '✗';
            $io->text("$status $feature");
        }

        // Получаем профили кодирования
        $profiles = $this->em->getRepository(VideoEncodingProfile::class)->findBy([], ['orderPosition' => 'ASC']);
        
        if (empty($profiles)) {
            $io->warning('Профили кодирования не найдены');
            return Command::SUCCESS;
        }

        $io->section('Доступные профили кодирования:');
        $profileRows = [];
        foreach ($profiles as $profile) {
            $profileRows[] = [
                $profile->getId(),
                $profile->getName(),
                $profile->getResolution(),
                $profile->getBitrate() . ' kbps',
                $profile->getCodec(),
                strtoupper($profile->getFormat() ?? 'MP4'),
                $profile->isActive() ? 'Да' : 'Нет'
            ];
        }
        
        $io->table(
            ['ID', 'Название', 'Разрешение', 'Битрейт', 'Кодек', 'Формат', 'Активен'],
            $profileRows
        );

        // Если указан конкретный профиль, тестируем его
        $profileId = $input->getOption('profile-id');
        if ($profileId) {
            $profile = $this->em->getRepository(VideoEncodingProfile::class)->find($profileId);
            if (!$profile) {
                $io->error("Профиль с ID $profileId не найден");
                return Command::FAILURE;
            }

            return $this->testProfile($io, $profile, $input->getOption('video-file'));
        }

        // Тестируем команды FFmpeg для каждого формата
        $io->section('Тестирование команд FFmpeg:');
        
        $testFormats = ['mp4', 'mov', 'avi', 'mkv'];
        foreach ($testFormats as $format) {
            $io->text("Тестирование формата: " . strtoupper($format));
            
            // Создаем тестовый профиль
            $testProfile = new VideoEncodingProfile();
            $testProfile->setName("Test $format");
            $testProfile->setResolution('1280x720');
            $testProfile->setBitrate(2500);
            $testProfile->setCodec('libx264');
            $testProfile->setFormat($format);
            
            $this->showFFmpegCommand($io, $testProfile);
        }

        $io->success('Тестирование завершено');
        return Command::SUCCESS;
    }

    private function testProfile(SymfonyStyle $io, VideoEncodingProfile $profile, ?string $videoFile): int
    {
        if (!$videoFile) {
            $io->error('Для тестирования профиля укажите путь к видеофайлу с опцией --video-file');
            return Command::FAILURE;
        }

        if (!file_exists($videoFile)) {
            $io->error("Видеофайл не найден: $videoFile");
            return Command::FAILURE;
        }

        $io->section("Тестирование профиля: {$profile->getName()}");
        
        // Создаем временный выходной файл
        $format = strtolower($profile->getFormat() ?: 'mp4');
        $extension = match($format) {
            'mp4' => 'mp4',
            'mov' => 'mov',
            'mkv' => 'mkv',
            'avi' => 'avi',
            default => 'mp4',
        };
        
        $outputFile = sys_get_temp_dir() . '/test_encoding_' . time() . '.' . $extension;
        
        $io->text("Входной файл: $videoFile");
        $io->text("Выходной файл: $outputFile");
        $io->text("Формат: " . strtoupper($format));
        
        try {
            $io->text('Начинаем кодирование...');
            $startTime = microtime(true);
            
            $success = $this->videoProcessor->encodeToProfile($videoFile, $outputFile, $profile);
            
            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);
            
            if ($success) {
                $fileSize = file_exists($outputFile) ? filesize($outputFile) : 0;
                $fileSizeMB = round($fileSize / 1024 / 1024, 2);
                
                $io->success("Кодирование успешно завершено за {$duration} сек");
                $io->text("Размер выходного файла: {$fileSizeMB} MB");
                
                // Удаляем тестовый файл
                if (file_exists($outputFile)) {
                    unlink($outputFile);
                    $io->text('Тестовый файл удален');
                }
            } else {
                $io->error("Кодирование не удалось за {$duration} сек");
            }
            
        } catch (\Exception $e) {
            $io->error("Ошибка кодирования: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function showFFmpegCommand(SymfonyStyle $io, VideoEncodingProfile $profile): void
    {
        // Симулируем команду FFmpeg которая будет сгенерирована
        $resolution = $profile->getResolution();
        if (preg_match('/(\d+)x(\d+)/', $resolution, $matches)) {
            $width = (int) $matches[1];
            $height = (int) $matches[2];
        } else {
            $width = 1280;
            $height = 720;
        }
        
        $bitrate = $profile->getBitrate();
        $codec = $profile->getCodec();
        $format = strtolower($profile->getFormat() ?: 'mp4');
        
        $command = [
            'ffmpeg',
            '-i', 'input.mp4',
            '-vf', "scale={$width}:{$height}:force_original_aspect_ratio=decrease,pad={$width}:{$height}:(ow-iw)/2:(oh-ih)/2",
            '-c:v', $codec,
            '-b:v', "{$bitrate}k",
        ];

        // Добавляем аудио кодек
        if ($format === 'mkv' && $codec === 'libx265') {
            $command = array_merge($command, ['-c:a', 'flac']);
        } elseif ($format === 'avi') {
            $command = array_merge($command, ['-c:a', 'mp3', '-b:a', '192k']);
        } else {
            $command = array_merge($command, ['-c:a', 'aac', '-b:a', '128k']);
        }

        $command = array_merge($command, ['-preset', 'medium']);
        
        // Добавляем параметры контейнера
        $containerParams = match($format) {
            'mp4' => ['-movflags', '+faststart'],
            'mov' => ['-movflags', '+faststart'],
            'mkv' => ['-f', 'matroska'],
            'avi' => ['-f', 'avi'],
            default => ['-movflags', '+faststart'],
        };
        
        $command = array_merge($command, $containerParams);
        $command = array_merge($command, ['-y', "output.$format"]);

        $io->text('Команда FFmpeg:');
        $io->text(implode(' ', $command));
        $io->newLine();
    }
}