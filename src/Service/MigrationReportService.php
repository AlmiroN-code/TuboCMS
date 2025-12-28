<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Сервис для отслеживания и отчётности о миграции файлов между хранилищами.
 * 
 * Requirement 4.5: WHEN migration completes THEN the System SHALL provide 
 * a summary report with success/failure counts
 * 
 * Property 9: Migration report accuracy
 * For any completed migration with S successes and F failures, 
 * the summary report SHALL show exactly S successful and F failed counts.
 */
class MigrationReportService
{
    private const CACHE_PREFIX = 'migration_report_';
    private const CACHE_TTL = 86400; // 24 часа

    private FilesystemAdapter $cache;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
        $this->cache = new FilesystemAdapter('migration_reports', self::CACHE_TTL);
    }

    /**
     * Создаёт новый отчёт о миграции.
     * 
     * @param string $migrationId Уникальный идентификатор миграции
     * @param int $totalFiles Общее количество файлов для миграции
     * @param string $sourceName Название источника
     * @param string $destinationName Название назначения
     */
    public function createReport(
        string $migrationId,
        int $totalFiles,
        string $sourceName,
        string $destinationName
    ): void {
        $report = [
            'id' => $migrationId,
            'totalFiles' => $totalFiles,
            'successCount' => 0,
            'failureCount' => 0,
            'sourceName' => $sourceName,
            'destinationName' => $destinationName,
            'startedAt' => (new \DateTimeImmutable())->format('c'),
            'completedAt' => null,
            'status' => 'in_progress',
            'failures' => [],
        ];

        $this->saveReport($migrationId, $report);

        $this->logger->info('Migration report created', [
            'migration_id' => $migrationId,
            'total_files' => $totalFiles,
            'source' => $sourceName,
            'destination' => $destinationName,
        ]);
    }

    /**
     * Регистрирует успешную миграцию файла.
     * 
     * @param string $migrationId Идентификатор миграции
     * @param int $videoFileId ID файла
     */
    public function recordSuccess(string $migrationId, int $videoFileId): void
    {
        $report = $this->getReport($migrationId);
        
        if ($report === null) {
            $this->logger->warning('Migration report not found for success recording', [
                'migration_id' => $migrationId,
                'video_file_id' => $videoFileId,
            ]);
            return;
        }

        $report['successCount']++;
        $this->updateReportStatus($report);
        $this->saveReport($migrationId, $report);

        $this->logger->debug('Migration success recorded', [
            'migration_id' => $migrationId,
            'video_file_id' => $videoFileId,
            'success_count' => $report['successCount'],
        ]);
    }

    /**
     * Регистрирует неудачную миграцию файла.
     * 
     * @param string $migrationId Идентификатор миграции
     * @param int $videoFileId ID файла
     * @param string $errorMessage Сообщение об ошибке
     */
    public function recordFailure(string $migrationId, int $videoFileId, string $errorMessage): void
    {
        $report = $this->getReport($migrationId);
        
        if ($report === null) {
            $this->logger->warning('Migration report not found for failure recording', [
                'migration_id' => $migrationId,
                'video_file_id' => $videoFileId,
            ]);
            return;
        }

        $report['failureCount']++;
        $report['failures'][] = [
            'videoFileId' => $videoFileId,
            'error' => $errorMessage,
            'timestamp' => (new \DateTimeImmutable())->format('c'),
        ];
        
        $this->updateReportStatus($report);
        $this->saveReport($migrationId, $report);

        $this->logger->debug('Migration failure recorded', [
            'migration_id' => $migrationId,
            'video_file_id' => $videoFileId,
            'failure_count' => $report['failureCount'],
            'error' => $errorMessage,
        ]);
    }


    /**
     * Получает отчёт о миграции.
     * 
     * @param string $migrationId Идентификатор миграции
     * @return array|null Данные отчёта или null если не найден
     */
    public function getReport(string $migrationId): ?array
    {
        try {
            $cacheKey = self::CACHE_PREFIX . $migrationId;
            $item = $this->cache->getItem($cacheKey);
            
            if (!$item->isHit()) {
                return null;
            }
            
            return $item->get();
        } catch (\Throwable $e) {
            $this->logger->error('Failed to get migration report', [
                'migration_id' => $migrationId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Получает сводку отчёта о миграции.
     * 
     * Requirement 4.5: WHEN migration completes THEN the System SHALL provide 
     * a summary report with success/failure counts
     * 
     * @param string $migrationId Идентификатор миграции
     * @return array Сводка отчёта
     */
    public function getSummary(string $migrationId): array
    {
        $report = $this->getReport($migrationId);
        
        if ($report === null) {
            return [
                'found' => false,
                'message' => 'Отчёт о миграции не найден',
            ];
        }

        $processedCount = $report['successCount'] + $report['failureCount'];
        $remainingCount = $report['totalFiles'] - $processedCount;
        $progressPercent = $report['totalFiles'] > 0 
            ? round(($processedCount / $report['totalFiles']) * 100, 1) 
            : 0;

        return [
            'found' => true,
            'id' => $report['id'],
            'status' => $report['status'],
            'totalFiles' => $report['totalFiles'],
            'successCount' => $report['successCount'],
            'failureCount' => $report['failureCount'],
            'processedCount' => $processedCount,
            'remainingCount' => $remainingCount,
            'progressPercent' => $progressPercent,
            'sourceName' => $report['sourceName'],
            'destinationName' => $report['destinationName'],
            'startedAt' => $report['startedAt'],
            'completedAt' => $report['completedAt'],
            'isComplete' => $report['status'] === 'completed',
            'hasFailures' => $report['failureCount'] > 0,
        ];
    }

    /**
     * Получает список неудачных миграций.
     * 
     * @param string $migrationId Идентификатор миграции
     * @return array Список ошибок
     */
    public function getFailures(string $migrationId): array
    {
        $report = $this->getReport($migrationId);
        
        if ($report === null) {
            return [];
        }

        return $report['failures'] ?? [];
    }

    /**
     * Получает список всех активных миграций.
     * 
     * @return array Список активных миграций
     */
    public function getActiveMigrations(): array
    {
        try {
            $activeMigrations = [];
            
            // Получаем список всех ключей из кэша
            // FilesystemAdapter не поддерживает итерацию, поэтому храним список ID отдельно
            $indexItem = $this->cache->getItem('migration_index');
            
            if (!$indexItem->isHit()) {
                return [];
            }
            
            $migrationIds = $indexItem->get() ?? [];
            
            foreach ($migrationIds as $migrationId) {
                $report = $this->getReport($migrationId);
                if ($report !== null && $report['status'] === 'in_progress') {
                    $activeMigrations[] = $this->getSummary($migrationId);
                }
            }
            
            return $activeMigrations;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to get active migrations', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Получает список последних завершённых миграций.
     * 
     * @param int $limit Максимальное количество
     * @return array Список завершённых миграций
     */
    public function getRecentCompletedMigrations(int $limit = 10): array
    {
        try {
            $completedMigrations = [];
            
            $indexItem = $this->cache->getItem('migration_index');
            
            if (!$indexItem->isHit()) {
                return [];
            }
            
            $migrationIds = $indexItem->get() ?? [];
            
            foreach ($migrationIds as $migrationId) {
                $report = $this->getReport($migrationId);
                if ($report !== null && $report['status'] === 'completed') {
                    $completedMigrations[] = $this->getSummary($migrationId);
                }
            }
            
            // Сортируем по дате завершения (новые первые)
            usort($completedMigrations, function ($a, $b) {
                return ($b['completedAt'] ?? '') <=> ($a['completedAt'] ?? '');
            });
            
            return array_slice($completedMigrations, 0, $limit);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to get completed migrations', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Генерирует уникальный идентификатор миграции.
     * 
     * @return string Уникальный идентификатор
     */
    public function generateMigrationId(): string
    {
        return uniqid('migration_', true);
    }

    /**
     * Удаляет отчёт о миграции.
     * 
     * @param string $migrationId Идентификатор миграции
     */
    public function deleteReport(string $migrationId): void
    {
        try {
            $cacheKey = self::CACHE_PREFIX . $migrationId;
            $this->cache->deleteItem($cacheKey);
            
            // Удаляем из индекса
            $this->removeFromIndex($migrationId);
            
            $this->logger->info('Migration report deleted', [
                'migration_id' => $migrationId,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to delete migration report', [
                'migration_id' => $migrationId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Обновляет статус отчёта на основе прогресса.
     */
    private function updateReportStatus(array &$report): void
    {
        $processedCount = $report['successCount'] + $report['failureCount'];
        
        if ($processedCount >= $report['totalFiles']) {
            $report['status'] = 'completed';
            $report['completedAt'] = (new \DateTimeImmutable())->format('c');
            
            $this->logger->info('Migration completed', [
                'migration_id' => $report['id'],
                'success_count' => $report['successCount'],
                'failure_count' => $report['failureCount'],
                'total_files' => $report['totalFiles'],
            ]);
        }
    }

    /**
     * Сохраняет отчёт в кэш.
     */
    private function saveReport(string $migrationId, array $report): void
    {
        try {
            $cacheKey = self::CACHE_PREFIX . $migrationId;
            $item = $this->cache->getItem($cacheKey);
            $item->set($report);
            $item->expiresAfter(self::CACHE_TTL);
            $this->cache->save($item);
            
            // Добавляем в индекс
            $this->addToIndex($migrationId);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to save migration report', [
                'migration_id' => $migrationId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Добавляет ID миграции в индекс.
     */
    private function addToIndex(string $migrationId): void
    {
        try {
            $indexItem = $this->cache->getItem('migration_index');
            $migrationIds = $indexItem->isHit() ? ($indexItem->get() ?? []) : [];
            
            if (!in_array($migrationId, $migrationIds, true)) {
                $migrationIds[] = $migrationId;
                $indexItem->set($migrationIds);
                $indexItem->expiresAfter(self::CACHE_TTL);
                $this->cache->save($indexItem);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Failed to add migration to index', [
                'migration_id' => $migrationId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Удаляет ID миграции из индекса.
     */
    private function removeFromIndex(string $migrationId): void
    {
        try {
            $indexItem = $this->cache->getItem('migration_index');
            
            if (!$indexItem->isHit()) {
                return;
            }
            
            $migrationIds = $indexItem->get() ?? [];
            $migrationIds = array_filter($migrationIds, fn($id) => $id !== $migrationId);
            
            $indexItem->set(array_values($migrationIds));
            $indexItem->expiresAfter(self::CACHE_TTL);
            $this->cache->save($indexItem);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to remove migration from index', [
                'migration_id' => $migrationId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
