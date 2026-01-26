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
    name: 'app:diagnose-encoding-profiles',
    description: 'Диагностирует проблемы с таблицей video_encoding_profile'
)]
class DiagnoseEncodingProfilesCommand extends Command
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

        $io->title('Диагностика таблицы video_encoding_profile');

        try {
            // 1. Проверяем существование таблицы
            $io->section('1. Проверка существования таблицы');
            
            $tableExists = $this->connection->createSchemaManager()->tablesExist(['video_encoding_profile']);
            
            if (!$tableExists) {
                $io->error('Таблица video_encoding_profile не существует!');
                return Command::FAILURE;
            }
            
            $io->success('Таблица video_encoding_profile существует');

            // 2. Проверяем структуру таблицы
            $io->section('2. Структура таблицы');
            
            $columns = $this->connection->createSchemaManager()->listTableColumns('video_encoding_profile');
            
            $expectedColumns = ['id', 'name', 'resolution', 'bitrate', 'codec', 'format', 'is_active', 'order_position'];
            $actualColumns = array_keys($columns);
            
            $io->text('Ожидаемые колонки: ' . implode(', ', $expectedColumns));
            $io->text('Фактические колонки: ' . implode(', ', $actualColumns));
            
            $missingColumns = array_diff($expectedColumns, $actualColumns);
            $extraColumns = array_diff($actualColumns, $expectedColumns);
            
            if (!empty($missingColumns)) {
                $io->error('Отсутствующие колонки: ' . implode(', ', $missingColumns));
                
                // Если отсутствует колонка format, предлагаем её добавить
                if (in_array('format', $missingColumns)) {
                    $io->warning('Колонка format отсутствует. Это может быть причиной ошибки 500.');
                    
                    if ($io->confirm('Добавить колонку format?', true)) {
                        $this->addFormatColumn($io);
                    }
                }
            } else {
                $io->success('Все необходимые колонки присутствуют');
            }
            
            if (!empty($extraColumns)) {
                $io->note('Дополнительные колонки: ' . implode(', ', $extraColumns));
            }

            // 3. Проверяем данные в таблице
            $io->section('3. Данные в таблице');
            
            $stmt = $this->connection->prepare('SELECT COUNT(*) as count FROM video_encoding_profile');
            $result = $stmt->executeQuery();
            $count = $result->fetchOne();
            
            $io->text("Количество записей: $count");
            
            if ($count > 0) {
                // Показываем первые несколько записей
                $stmt = $this->connection->prepare('SELECT * FROM video_encoding_profile LIMIT 5');
                $result = $stmt->executeQuery();
                $profiles = $result->fetchAllAssociative();
                
                $rows = [];
                foreach ($profiles as $profile) {
                    $rows[] = [
                        $profile['id'] ?? 'N/A',
                        $profile['name'] ?? 'N/A',
                        $profile['resolution'] ?? 'N/A',
                        $profile['bitrate'] ?? 'N/A',
                        $profile['codec'] ?? 'N/A',
                        $profile['format'] ?? 'N/A',
                        isset($profile['is_active']) ? ($profile['is_active'] ? 'Да' : 'Нет') : 'N/A'
                    ];
                }
                
                $io->table(
                    ['ID', 'Название', 'Разрешение', 'Битрейт', 'Кодек', 'Формат', 'Активен'],
                    $rows
                );
            }

            // 4. Проверяем статус миграций
            $io->section('4. Статус миграций');
            
            $stmt = $this->connection->prepare('SELECT version FROM doctrine_migration_versions ORDER BY version DESC LIMIT 10');
            $result = $stmt->executeQuery();
            $migrations = $result->fetchAllAssociative();
            
            $io->text('Последние применённые миграции:');
            foreach ($migrations as $migration) {
                $io->text('- ' . $migration['version']);
            }
            
            // Проверяем конкретную миграцию для format
            $formatMigrationExists = false;
            foreach ($migrations as $migration) {
                if (strpos($migration['version'], '20260123000001') !== false) {
                    $formatMigrationExists = true;
                    break;
                }
            }
            
            if (!$formatMigrationExists) {
                $io->warning('Миграция Version20260123000001 (добавление поля format) не применена!');
                $io->text('Выполните: php bin/console doctrine:migrations:migrate --no-interaction');
            } else {
                $io->success('Миграция для поля format применена');
            }

            // 5. Тестируем доступ через Doctrine
            $io->section('5. Тест доступа через Doctrine');
            
            try {
                $profiles = $this->em->getRepository(\App\Entity\VideoEncodingProfile::class)->findAll();
                $io->success('Doctrine успешно получил ' . count($profiles) . ' профилей');
                
                if (!empty($profiles)) {
                    $firstProfile = $profiles[0];
                    $io->text('Тест первого профиля:');
                    $io->text('- ID: ' . $firstProfile->getId());
                    $io->text('- Название: ' . $firstProfile->getName());
                    $io->text('- Формат: ' . ($firstProfile->getFormat() ?? 'NULL'));
                }
            } catch (\Exception $e) {
                $io->error('Ошибка Doctrine: ' . $e->getMessage());
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $io->error('Ошибка диагностики: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->success('Диагностика завершена');
        return Command::SUCCESS;
    }

    private function addFormatColumn(SymfonyStyle $io): void
    {
        try {
            $io->text('Добавляем колонку format...');
            
            $sql = "ALTER TABLE video_encoding_profile ADD COLUMN format VARCHAR(10) NOT NULL DEFAULT 'mp4'";
            $this->connection->executeStatement($sql);
            
            $io->success('Колонка format успешно добавлена');
            
            // Обновляем существующие записи
            $updateSql = "UPDATE video_encoding_profile SET format = 'mp4' WHERE format = ''";
            $this->connection->executeStatement($updateSql);
            
            $io->text('Существующие записи обновлены со значением format = mp4');
            
        } catch (\Exception $e) {
            $io->error('Ошибка при добавлении колонки: ' . $e->getMessage());
        }
    }
}