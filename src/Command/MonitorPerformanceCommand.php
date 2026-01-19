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
    name: 'app:monitor-performance',
    description: 'Мониторинг производительности базы данных'
)]
class MonitorPerformanceCommand extends Command
{
    public function __construct(
        private Connection $connection
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('slow-queries', null, InputOption::VALUE_NONE, 'Показать медленные запросы')
            ->addOption('index-usage', null, InputOption::VALUE_NONE, 'Показать использование индексов')
            ->addOption('table-stats', null, InputOption::VALUE_NONE, 'Показать статистику таблиц')
            ->addOption('cache-stats', null, InputOption::VALUE_NONE, 'Показать статистику кеша');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Мониторинг производительности RexTube');

        if ($input->getOption('slow-queries')) {
            $this->showSlowQueries($io);
        }

        if ($input->getOption('index-usage')) {
            $this->showIndexUsage($io);
        }

        if ($input->getOption('table-stats')) {
            $this->showTableStats($io);
        }

        if ($input->getOption('cache-stats')) {
            $this->showCacheStats($io);
        }

        // Если не указаны опции, показываем общую статистику
        if (!$input->getOption('slow-queries') && 
            !$input->getOption('index-usage') && 
            !$input->getOption('table-stats') && 
            !$input->getOption('cache-stats')) {
            $this->showGeneralStats($io);
        }

        return Command::SUCCESS;
    }

    private function showSlowQueries(SymfonyStyle $io): void
    {
        $io->section('Медленные запросы (> 1 секунды)');

        try {
            // Включаем логирование медленных запросов
            $this->connection->executeStatement("SET SESSION long_query_time = 1");
            
            $io->note('Логирование медленных запросов включено. Проверьте логи MySQL для деталей.');
            
            // Показываем текущие процессы
            $processes = $this->connection->fetchAllAssociative("SHOW PROCESSLIST");
            
            $slowProcesses = array_filter($processes, fn($p) => $p['Time'] > 1);
            
            if (empty($slowProcesses)) {
                $io->success('Медленных запросов не обнаружено');
            } else {
                $io->table(
                    ['ID', 'User', 'Host', 'DB', 'Command', 'Time', 'State', 'Info'],
                    array_map(fn($p) => [
                        $p['Id'],
                        $p['User'],
                        $p['Host'],
                        $p['db'],
                        $p['Command'],
                        $p['Time'] . 's',
                        $p['State'],
                        substr($p['Info'] ?? '', 0, 50) . '...'
                    ], $slowProcesses)
                );
            }
        } catch (\Exception $e) {
            $io->error('Ошибка при получении медленных запросов: ' . $e->getMessage());
        }
    }

    private function showIndexUsage(SymfonyStyle $io): void
    {
        $io->section('Использование индексов');

        try {
            // Проверяем индексы таблицы video
            $indexes = $this->connection->fetchAllAssociative("SHOW INDEX FROM video");
            
            $io->table(
                ['Таблица', 'Индекс', 'Колонка', 'Уникальный', 'Тип'],
                array_map(fn($idx) => [
                    $idx['Table'],
                    $idx['Key_name'],
                    $idx['Column_name'],
                    $idx['Non_unique'] ? 'Нет' : 'Да',
                    $idx['Index_type']
                ], $indexes)
            );

            // Проверяем неиспользуемые индексы
            $unusedIndexes = $this->connection->fetchAllAssociative("
                SELECT OBJECT_SCHEMA, OBJECT_NAME, INDEX_NAME 
                FROM performance_schema.table_io_waits_summary_by_index_usage 
                WHERE INDEX_NAME IS NOT NULL 
                AND COUNT_STAR = 0 
                AND OBJECT_SCHEMA = DATABASE()
                AND OBJECT_NAME = 'video'
            ");

            if (!empty($unusedIndexes)) {
                $io->warning('Неиспользуемые индексы:');
                foreach ($unusedIndexes as $idx) {
                    $io->text("- {$idx['OBJECT_NAME']}.{$idx['INDEX_NAME']}");
                }
            }

        } catch (\Exception $e) {
            $io->error('Ошибка при анализе индексов: ' . $e->getMessage());
        }
    }

    private function showTableStats(SymfonyStyle $io): void
    {
        $io->section('Статистика таблиц');

        try {
            $stats = $this->connection->fetchAllAssociative("
                SELECT 
                    TABLE_NAME as 'Таблица',
                    TABLE_ROWS as 'Строк',
                    ROUND(DATA_LENGTH / 1024 / 1024, 2) as 'Размер (MB)',
                    ROUND(INDEX_LENGTH / 1024 / 1024, 2) as 'Индексы (MB)',
                    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) as 'Всего (MB)'
                FROM information_schema.TABLES 
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME IN ('video', 'user', 'category', 'tag', 'comment', 'video_file')
                ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC
            ");

            $io->table(
                ['Таблица', 'Строк', 'Размер (MB)', 'Индексы (MB)', 'Всего (MB)'],
                $stats
            );

        } catch (\Exception $e) {
            $io->error('Ошибка при получении статистики таблиц: ' . $e->getMessage());
        }
    }

    private function showCacheStats(SymfonyStyle $io): void
    {
        $io->section('Статистика кеша MySQL');

        try {
            $cacheStats = $this->connection->fetchAllAssociative("
                SHOW STATUS LIKE 'Qcache%'
            ");

            if (empty($cacheStats)) {
                $io->note('Query Cache отключен или недоступен');
                return;
            }

            $stats = [];
            foreach ($cacheStats as $stat) {
                $stats[$stat['Variable_name']] = $stat['Value'];
            }

            $io->table(
                ['Параметр', 'Значение'],
                [
                    ['Размер кеша', $stats['Qcache_total_blocks'] ?? 'N/A'],
                    ['Свободные блоки', $stats['Qcache_free_blocks'] ?? 'N/A'],
                    ['Запросов в кеше', $stats['Qcache_queries_in_cache'] ?? 'N/A'],
                    ['Попаданий', $stats['Qcache_hits'] ?? 'N/A'],
                    ['Промахов', $stats['Qcache_not_cached'] ?? 'N/A'],
                ]
            );

        } catch (\Exception $e) {
            $io->error('Ошибка при получении статистики кеша: ' . $e->getMessage());
        }
    }

    private function showGeneralStats(SymfonyStyle $io): void
    {
        $io->section('Общая статистика производительности');

        try {
            // Количество видео
            $videoCount = $this->connection->fetchOne("SELECT COUNT(*) FROM video WHERE status = 'published'");
            $totalVideos = $this->connection->fetchOne("SELECT COUNT(*) FROM video");
            
            // Количество пользователей
            $userCount = $this->connection->fetchOne("SELECT COUNT(*) FROM user");
            
            // Количество комментариев
            $commentCount = $this->connection->fetchOne("SELECT COUNT(*) FROM comment");

            $io->table(
                ['Метрика', 'Значение'],
                [
                    ['Опубликованных видео', number_format($videoCount)],
                    ['Всего видео', number_format($totalVideos)],
                    ['Пользователей', number_format($userCount)],
                    ['Комментариев', number_format($commentCount)],
                ]
            );

            // Топ популярных видео
            $topVideos = $this->connection->fetchAllAssociative("
                SELECT title, views_count, created_at 
                FROM video 
                WHERE status = 'published' 
                ORDER BY views_count DESC 
                LIMIT 5
            ");

            if (!empty($topVideos)) {
                $io->section('Топ 5 популярных видео');
                $io->table(
                    ['Название', 'Просмотры', 'Дата создания'],
                    array_map(fn($v) => [
                        substr($v['title'], 0, 50) . '...',
                        number_format($v['views_count']),
                        $v['created_at']
                    ], $topVideos)
                );
            }

        } catch (\Exception $e) {
            $io->error('Ошибка при получении общей статистики: ' . $e->getMessage());
        }
    }
}