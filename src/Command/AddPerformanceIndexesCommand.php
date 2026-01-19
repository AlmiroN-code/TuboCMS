<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:add-performance-indexes',
    description: 'Добавляет индексы для оптимизации производительности'
)]
class AddPerformanceIndexesCommand extends Command
{
    public function __construct(
        private Connection $connection
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $indexes = [
            'idx_video_featured_status' => 'CREATE INDEX idx_video_featured_status ON video (is_featured, status)',
            'idx_video_duration' => 'CREATE INDEX idx_video_duration ON video (duration)',
            'idx_video_created_by' => 'CREATE INDEX idx_video_created_by ON video (created_by_id)',
            'idx_video_search' => 'CREATE FULLTEXT INDEX idx_video_search ON video (title, description)',
            'idx_video_likes' => 'CREATE INDEX idx_video_likes ON video (likes_count, dislikes_count)',
            'idx_video_impressions' => 'CREATE INDEX idx_video_impressions ON video (impressions_count)',
            'idx_video_processing' => 'CREATE INDEX idx_video_processing ON video (processing_status)',
        ];

        $io->title('Добавление индексов для оптимизации производительности');

        foreach ($indexes as $indexName => $sql) {
            try {
                // Проверяем, существует ли индекс
                $existingIndexes = $this->connection->fetchAllAssociative(
                    "SHOW INDEX FROM video WHERE Key_name = ?",
                    [$indexName]
                );

                if (empty($existingIndexes)) {
                    $this->connection->executeStatement($sql);
                    $io->success("Индекс {$indexName} успешно создан");
                } else {
                    $io->note("Индекс {$indexName} уже существует");
                }
            } catch (\Exception $e) {
                $io->error("Ошибка при создании индекса {$indexName}: " . $e->getMessage());
            }
        }

        $io->success('Процесс добавления индексов завершен');

        return Command::SUCCESS;
    }
}