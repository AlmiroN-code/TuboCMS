<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fix-encoding-profiles-production',
    description: 'Исправляет проблемы с профилями кодирования в продакшене'
)]
class FixEncodingProfilesProductionCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private Connection $connection
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Исправление профилей кодирования для продакшена');

        try {
            // 1. Проверяем и добавляем колонку format если её нет
            $io->section('1. Проверка колонки format');
            
            $columns = $this->connection->createSchemaManager()->listTableColumns('video_encoding_profile');
            
            if (!isset($columns['format'])) {
                $io->warning('Колонка format отсутствует. Добавляем...');
                
                $sql = "ALTER TABLE video_encoding_profile ADD COLUMN format VARCHAR(10) NOT NULL DEFAULT 'mp4'";
                $this->connection->executeStatement($sql);
                
                $io->success('Колонка format добавлена');
            } else {
                $io->success('Колонка format уже существует');
            }

            // 2. Обновляем пустые значения format
            $io->section('2. Обновление значений format');
            
            $updateSql = "UPDATE video_encoding_profile SET format = 'mp4' WHERE format IS NULL OR format = ''";
            $affectedRows = $this->connection->executeStatement($updateSql);
            
            if ($affectedRows > 0) {
                $io->success("Обновлено $affectedRows записей со значением format = 'mp4'");
            } else {
                $io->text('Все записи уже имеют корректные значения format');
            }

            // 3. Проверяем и создаём базовые профили если их нет
            $io->section('3. Проверка базовых профилей');
            
            $stmt = $this->connection->prepare('SELECT COUNT(*) as count FROM video_encoding_profile');
            $result = $stmt->executeQuery();
            $count = $result->fetchOne();
            
            if ($count == 0) {
                $io->warning('Профили кодирования отсутствуют. Создаём базовые...');
                $this->createBasicProfiles($io);
            } else {
                $io->success("Найдено $count профилей кодирования");
            }

            // 4. Обновляем кодеки на правильные значения FFmpeg
            $io->section('4. Обновление кодеков');
            
            $codecUpdates = [
                "UPDATE video_encoding_profile SET codec = 'libx264' WHERE codec IN ('h264', 'x264', 'avc')",
                "UPDATE video_encoding_profile SET codec = 'libx265' WHERE codec IN ('h265', 'x265', 'hevc')",
                "UPDATE video_encoding_profile SET codec = 'libvpx-vp9' WHERE codec = 'vp9'",
                "UPDATE video_encoding_profile SET codec = 'libaom-av1' WHERE codec = 'av1'"
            ];
            
            $totalUpdated = 0;
            foreach ($codecUpdates as $updateSql) {
                $affected = $this->connection->executeStatement($updateSql);
                $totalUpdated += $affected;
            }
            
            if ($totalUpdated > 0) {
                $io->success("Обновлено $totalUpdated кодеков на правильные значения FFmpeg");
            } else {
                $io->text('Все кодеки уже имеют правильные значения');
            }

            // 5. Очищаем кеш
            $io->section('5. Очистка кеша');
            
            // Очищаем кеш Doctrine
            $this->em->clear();
            
            $io->success('Кеш Doctrine очищен');

            // 6. Финальная проверка
            $io->section('6. Финальная проверка');
            
            try {
                $profiles = $this->em->getRepository(\App\Entity\VideoEncodingProfile::class)->findAll();
                $io->success('✓ Doctrine успешно получил ' . count($profiles) . ' профилей');
                
                if (!empty($profiles)) {
                    $io->text('Примеры профилей:');
                    foreach (array_slice($profiles, 0, 3) as $profile) {
                        $io->text(sprintf(
                            '- %s: %s, %s kbps, %s, %s',
                            $profile->getName(),
                            $profile->getResolution(),
                            $profile->getBitrate(),
                            $profile->getCodec(),
                            strtoupper($profile->getFormat() ?? 'MP4')
                        ));
                    }
                }
            } catch (\Exception $e) {
                $io->error('Ошибка при финальной проверке: ' . $e->getMessage());
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $io->error('Ошибка исправления: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->success('Исправление завершено успешно!');
        $io->note('Теперь страница /admin/settings/encoding-profiles должна работать корректно.');
        
        return Command::SUCCESS;
    }

    private function createBasicProfiles(SymfonyStyle $io): void
    {
        $profiles = [
            ['Full HD 1080p', '1920x1080', 8000, 'libx264', 'mp4', 1, 1],
            ['HD 720p', '1280x720', 5000, 'libx264', 'mp4', 1, 2],
            ['SD 480p', '854x480', 2500, 'libx264', 'mp4', 1, 3],
            ['Mobile 360p', '640x360', 1000, 'libx264', 'mp4', 1, 4],
        ];

        $insertSql = "INSERT INTO video_encoding_profile (name, resolution, bitrate, codec, format, is_active, order_position) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        foreach ($profiles as $profile) {
            $this->connection->executeStatement($insertSql, $profile);
        }
        
        $io->success('Создано ' . count($profiles) . ' базовых профилей кодирования');
    }
}