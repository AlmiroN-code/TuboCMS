<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fix-video-model-table',
    description: 'Исправить таблицу video_model (model_id -> model_profile_id)',
)]
class FixVideoModelTableCommand extends Command
{
    public function __construct(
        private Connection $connection
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Выполнить исправление без подтверждения');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');

        $io->title('Исправление таблицы video_model');

        // Проверяем текущую структуру таблицы
        try {
            $columns = $this->connection->fetchAllAssociative("DESCRIBE video_model");
            $hasModelId = false;
            $hasModelProfileId = false;

            foreach ($columns as $column) {
                if ($column['Field'] === 'model_id') {
                    $hasModelId = true;
                }
                if ($column['Field'] === 'model_profile_id') {
                    $hasModelProfileId = true;
                }
            }

            $io->section('Текущая структура таблицы video_model:');
            $io->table(['Field', 'Type', 'Null', 'Key', 'Default', 'Extra'], $columns);

            if ($hasModelProfileId && !$hasModelId) {
                $io->success('Таблица video_model уже имеет правильную структуру!');
                return Command::SUCCESS;
            }

            if (!$hasModelId) {
                $io->error('Таблица video_model не найдена или имеет неожиданную структуру');
                return Command::FAILURE;
            }

            // Показываем количество записей
            $count = $this->connection->fetchOne("SELECT COUNT(*) FROM video_model");
            $io->writeln(sprintf('Найдено записей в таблице: %d', $count));

            if (!$force) {
                if (!$io->confirm('Пересоздать таблицу video_model? Все данные будут потеряны!', false)) {
                    $io->info('Операция отменена');
                    return Command::SUCCESS;
                }
            }

        } catch (\Exception $e) {
            $io->error('Ошибка при проверке таблицы: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // Исправляем таблицу
        try {
            $io->writeln('🔄 Пересоздаю таблицу video_model...');

            // Удаляем старую таблицу
            $this->connection->executeStatement('DROP TABLE IF EXISTS video_model');

            // Создаем новую таблицу с правильной структурой
            $sql = "
                CREATE TABLE video_model (
                    video_id INT NOT NULL,
                    model_profile_id INT NOT NULL,
                    PRIMARY KEY (video_id, model_profile_id),
                    INDEX IDX_video_model_video (video_id),
                    INDEX IDX_video_model_model (model_profile_id),
                    FOREIGN KEY (video_id) REFERENCES video (id) ON DELETE CASCADE,
                    FOREIGN KEY (model_profile_id) REFERENCES model_profile (id) ON DELETE CASCADE
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            ";

            $this->connection->executeStatement($sql);

            $io->success([
                '✅ Таблица video_model успешно пересоздана!',
                '',
                '📋 Новая структура:',
                '   - video_id INT NOT NULL',
                '   - model_profile_id INT NOT NULL (исправлено с model_id)',
                '   - PRIMARY KEY (video_id, model_profile_id)',
                '   - Внешние ключи настроены правильно'
            ]);

            // Проверяем новую структуру
            $newColumns = $this->connection->fetchAllAssociative("DESCRIBE video_model");
            $io->section('Новая структура таблицы:');
            $io->table(['Field', 'Type', 'Null', 'Key', 'Default', 'Extra'], $newColumns);

            $io->note([
                'Теперь страницы /videos/, /categories/* и /models/* должны работать без ошибок 500.',
                'Данные о связях видео-модели нужно будет заполнить заново через админ-панель.'
            ]);

        } catch (\Exception $e) {
            $io->error([
                'Ошибка при исправлении таблицы:',
                $e->getMessage()
            ]);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}