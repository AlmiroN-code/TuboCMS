<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;

class PerformanceMonitorService
{
    private array $queryTimes = [];
    private float $startTime;

    public function __construct(
        private Connection $connection,
        private CacheInterface $cache,
        private LoggerInterface $logger
    ) {
        $this->startTime = microtime(true);
    }

    /**
     * Логирует медленный запрос
     */
    public function logSlowQuery(string $query, float $executionTime, array $params = []): void
    {
        if ($executionTime > 1.0) { // Запросы медленнее 1 секунды
            $this->logger->warning('Медленный запрос обнаружен', [
                'query' => substr($query, 0, 200) . '...',
                'execution_time' => $executionTime,
                'params' => $params,
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true)
            ]);
        }
    }

    /**
     * Отслеживает время выполнения запроса
     */
    public function trackQuery(string $queryType, callable $callback)
    {
        $startTime = microtime(true);
        
        try {
            $result = $callback();
            $executionTime = microtime(true) - $startTime;
            
            $this->queryTimes[$queryType] = ($this->queryTimes[$queryType] ?? 0) + $executionTime;
            
            if ($executionTime > 0.5) { // Логируем запросы медленнее 0.5 секунды
                $this->logger->info('Медленный запрос', [
                    'type' => $queryType,
                    'execution_time' => $executionTime
                ]);
            }
            
            return $result;
        } catch (\Exception $e) {
            $executionTime = microtime(true) - $startTime;
            $this->logger->error('Ошибка выполнения запроса', [
                'type' => $queryType,
                'execution_time' => $executionTime,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Получает статистику производительности
     */
    public function getPerformanceStats(): array
    {
        $totalTime = microtime(true) - $this->startTime;
        
        return [
            'total_execution_time' => $totalTime,
            'query_times' => $this->queryTimes,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'queries_count' => count($this->queryTimes)
        ];
    }

    /**
     * Проверяет состояние кеша
     */
    public function getCacheStats(): array
    {
        try {
            // Тестируем производительность кеша
            $testKey = 'performance_test_' . uniqid();
            $testData = ['test' => 'data', 'timestamp' => time()];
            
            $writeStart = microtime(true);
            $this->cache->get($testKey, fn() => $testData);
            $writeTime = microtime(true) - $writeStart;
            
            $readStart = microtime(true);
            $this->cache->get($testKey, fn() => $testData);
            $readTime = microtime(true) - $readStart;
            
            // Очищаем тестовый ключ
            $this->cache->delete($testKey);
            
            return [
                'cache_write_time' => $writeTime,
                'cache_read_time' => $readTime,
                'cache_available' => true
            ];
        } catch (\Exception $e) {
            return [
                'cache_available' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Проверяет состояние базы данных
     */
    public function getDatabaseStats(): array
    {
        try {
            $startTime = microtime(true);
            $result = $this->connection->fetchAssociative("SELECT 1 as test");
            $connectionTime = microtime(true) - $startTime;
            
            // Получаем статистику подключений
            $processlist = $this->connection->fetchAllAssociative("SHOW PROCESSLIST");
            $activeConnections = count(array_filter($processlist, fn($p) => $p['Command'] !== 'Sleep'));
            
            return [
                'connection_time' => $connectionTime,
                'active_connections' => $activeConnections,
                'total_connections' => count($processlist),
                'database_available' => true
            ];
        } catch (\Exception $e) {
            return [
                'database_available' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Генерирует отчет о производительности
     */
    public function generatePerformanceReport(): array
    {
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'performance' => $this->getPerformanceStats(),
            'cache' => $this->getCacheStats(),
            'database' => $this->getDatabaseStats(),
            'system' => [
                'php_version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'opcache_enabled' => function_exists('opcache_get_status') && (opcache_get_status()['opcache_enabled'] ?? false)
            ]
        ];
    }

    /**
     * Сохраняет отчет о производительности
     */
    public function savePerformanceReport(): void
    {
        $report = $this->generatePerformanceReport();
        
        // Сохраняем в лог
        $this->logger->info('Отчет о производительности', $report);
        
        // Сохраняем в кеш для мониторинга
        $this->cache->get(
            'performance_report_' . date('Y-m-d-H'),
            fn() => $report,
            3600 // 1 час
        );
    }
}