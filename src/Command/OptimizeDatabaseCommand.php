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
    name: 'app:optimize-database',
    description: 'Оптимизирует базу данных (ANALYZE, OPTIMIZE таблицы)'
)]
class OptimizeDatabaseCommand extends Command
{
    public function __construct(
        private readonly Connection $connection
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('analyze-only', null, InputOption::VALUE_NONE, 'Только анализ таблиц без оптимизации')
            ->addOption('tables', null, InputOption::VALUE_OPTIONAL, 'Конкретные таблицы через запятую');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $analyzeOnly = $input->getOption('analyze-only');
        $specificTables = $input->getOption('tables');
        
        $io->title('Оптимизация базы данных');

        try {
            // Получаем список таблиц
            if ($specificTables) {
                $tables = array_map('trim', explode(',', $specificTables));
            } else {
                $tables = $this->getAllTables();
            }

            if (empty($tables)) {
                $io->error('Таблицы не найдены');
                return Command::FAILURE;
            }

            $io->text(sprintf('Найдено %d таблиц для обработки', count($tables)));

            // Анализируем таблицы
            $io->section('Анализ таблиц');
            $this->analyzeTables($tables, $io);

            if (!$analyzeOnly) {
                // Оптимизируем таблицы
                $io->section('Оптимизация таблиц');
                $this->optimizeTables($tables, $io);
            }

            // Показываем статистику
            $io->section('Статистика базы данных');
            $this->showDatabaseStats($io);

            $io->success('Оптимизация базы данных завершена');
            
        } catch (\Exception $e) {
            $io->error('Ошибка при оптимизации: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function getAllTables(): array
    {
        $schemaManager = $this->connection->createSchemaManager();
        return $schemaManager->listTableNames();
    }

    private function analyzeTables(array $tables, SymfonyStyle $io): void
    {
        foreach ($tables as $table) {
            try {
                $io->text("Анализ таблицы: {$table}");
                
                $this->connection->executeStatement("ANALYZE TABLE `{$table}`");
                
                // Получаем информацию о таблице
                $tableInfo = $this->getTableInfo($table);
                
                if ($tableInfo) {
                    $io->text(sprintf(
                        "  Строк: %s, Размер: %s, Индексы: %s",
                        number_format($tableInfo['rows']),
                        $this->formatBytes($tableInfo['data_length']),
                        $this->formatBytes($tableInfo['index_length'])
                    ));
                }
                
            } catch (\Exception $e) {
                $io->warning("Ошибка анализа таблицы {$table}: " . $e->getMessage());
            }
        }
    }

    private function optimizeTables(array $tables, SymfonyStyle $io): void
    {
        foreach ($tables as $table) {
            try {
                $io->text("Оптимизация таблицы: {$table}");
                
                $result = $this->connection->executeQuery("OPTIMIZE TABLE `{$table}`");
                $optimizeResult = $result->fetchAssociative();
                
                if ($optimizeResult && isset($optimizeResult['Msg_text'])) {
                    $io->text("  Результат: " . $optimizeResult['Msg_text']);
                }
                
            } catch (\Exception $e) {
                $io->warning("Ошибка оптимизации таблицы {$table}: " . $e->getMessage());
            }
        }
    }

    private function getTableInfo(string $table): ?array
    {
        try {
            $result = $this->connection->executeQuery(
                "SELECT 
                    table_rows as rows,
                    data_length,
                    index_length,
                    (data_length + index_length) as total_size
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                AND table_name = ?",
                [$table]
            );
            
            return $result->fetchAssociative() ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function showDatabaseStats(SymfonyStyle $io): void
    {
        try {
            // Общая статистика БД
            $dbStats = $this->connection->executeQuery(
                "SELECT 
                    COUNT(*) as table_count,
                    SUM(table_rows) as total_rows,
                    SUM(data_length) as total_data_size,
                    SUM(index_length) as total_index_size,
                    SUM(data_length + index_length) as total_size
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()"
            )->fetchAssociative();

            if ($dbStats) {
                $io->definitionList(
                    ['Количество таблиц' => number_format($dbStats['table_count'])],
                    ['Общее количество строк' => number_format($dbStats['total_rows'])],
                    ['Размер данных' => $this->formatBytes($dbStats['total_data_size'])],
                    ['Размер индексов' => $this->formatBytes($dbStats['total_index_size'])],
                    ['Общий размер' => $this->formatBytes($dbStats['total_size'])]
                );
            }

            // Топ-5 самых больших таблиц
            $biggestTables = $this->connection->executeQuery(
                "SELECT 
                    table_name,
                    table_rows,
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
                ORDER BY (data_length + index_length) DESC 
                LIMIT 5"
            )->fetchAllAssociative();

            if ($biggestTables) {
                $io->text('Самые большие таблицы:');
                $tableData = [];
                foreach ($biggestTables as $table) {
                    $tableData[] = [
                        $table['table_name'],
                        number_format($table['table_rows']),
                        $table['size_mb'] . ' MB'
                    ];
                }
                $io->table(['Таблица', 'Строк', 'Размер'], $tableData);
            }

        } catch (\Exception $e) {
            $io->warning('Не удалось получить статистику БД: ' . $e->getMessage());
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}