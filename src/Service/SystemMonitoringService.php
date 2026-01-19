<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\VideoRepository;
use App\Repository\StorageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Сервис для мониторинга состояния системы
 */
class SystemMonitoringService
{
    public function __construct(
        private readonly VideoProcessingService $videoProcessingService,
        private readonly StorageManager $storageManager,
        private readonly VideoRepository $videoRepository,
        private readonly StorageRepository $storageRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Проверить общее состояние системы
     */
    public function getSystemHealth(): array
    {
        $health = [
            'overall_status' => 'healthy',
            'checks' => [],
            'timestamp' => new \DateTime(),
        ];

        // Проверка FFmpeg
        $ffmpegCheck = $this->checkFFmpegHealth();
        $health['checks']['ffmpeg'] = $ffmpegCheck;
        
        // Проверка базы данных
        $databaseCheck = $this->checkDatabaseHealth();
        $health['checks']['database'] = $databaseCheck;
        
        // Проверка хранилищ
        $storageCheck = $this->checkStorageHealth();
        $health['checks']['storage'] = $storageCheck;
        
        // Проверка дискового пространства
        $diskCheck = $this->checkDiskSpace();
        $health['checks']['disk_space'] = $diskCheck;
        
        // Проверка очереди обработки
        $queueCheck = $this->checkProcessingQueue();
        $health['checks']['processing_queue'] = $queueCheck;

        // Определяем общий статус
        $hasErrors = false;
        $hasWarnings = false;
        
        foreach ($health['checks'] as $check) {
            if ($check['status'] === 'error') {
                $hasErrors = true;
            } elseif ($check['status'] === 'warning') {
                $hasWarnings = true;
            }
        }

        if ($hasErrors) {
            $health['overall_status'] = 'error';
        } elseif ($hasWarnings) {
            $health['overall_status'] = 'warning';
        }

        return $health;
    }

    /**
     * Проверка состояния FFmpeg
     */
    private function checkFFmpegHealth(): array
    {
        try {
            $isAvailable = $this->videoProcessingService->isFFmpegAvailable();
            $requirements = $this->videoProcessingService->checkSystemRequirements();
            $capabilities = $this->videoProcessingService->getCapabilities();

            $status = 'healthy';
            $issues = [];

            if (!$isAvailable) {
                $status = 'error';
                $issues[] = 'FFmpeg не доступен';
            }

            if (!$requirements['disk_space']) {
                $status = 'warning';
                $issues[] = 'Недостаточно места на диске для обработки видео';
            }

            if (!$requirements['memory_limit']) {
                $status = 'warning';
                $issues[] = 'Недостаточно памяти для обработки видео';
            }

            if (!$requirements['temp_dir_writable']) {
                $status = 'error';
                $issues[] = 'Временная директория недоступна для записи';
            }

            return [
                'status' => $status,
                'available' => $isAvailable,
                'capabilities' => $capabilities,
                'requirements' => $requirements,
                'issues' => $issues,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'available' => false,
                'error' => $e->getMessage(),
                'issues' => ['Ошибка при проверке FFmpeg: ' . $e->getMessage()],
            ];
        }
    }

    /**
     * Проверка состояния базы данных
     */
    private function checkDatabaseHealth(): array
    {
        try {
            // Проверяем соединение
            $connection = $this->entityManager->getConnection();
            $connection->executeQuery('SELECT 1');

            // Проверяем количество видео в обработке
            $processingCount = $this->videoRepository->count(['status' => 'processing']);
            
            // Проверяем количество неудачных обработок
            $failedCount = $this->videoRepository->count(['status' => 'rejected']);

            $status = 'healthy';
            $issues = [];

            if ($processingCount > 100) {
                $status = 'warning';
                $issues[] = "Много видео в обработке: {$processingCount}";
            }

            if ($failedCount > 50) {
                $status = 'warning';
                $issues[] = "Много неудачных обработок: {$failedCount}";
            }

            return [
                'status' => $status,
                'connected' => true,
                'processing_videos' => $processingCount,
                'failed_videos' => $failedCount,
                'issues' => $issues,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'connected' => false,
                'error' => $e->getMessage(),
                'issues' => ['Ошибка подключения к БД: ' . $e->getMessage()],
            ];
        }
    }

    /**
     * Проверка состояния хранилищ
     */
    private function checkStorageHealth(): array
    {
        try {
            $storages = $this->storageRepository->findBy(['isEnabled' => true]);
            $storageStats = [];
            $overallStatus = 'healthy';
            $issues = [];

            foreach ($storages as $storage) {
                try {
                    $adapter = $this->storageManager->getAdapter($storage);
                    $connectionResult = $adapter->testConnection();
                    $quota = $adapter->getQuota();
                    
                    $stats = [
                        'id' => $storage->getId(),
                        'is_healthy' => $connectionResult->success,
                        'type' => $storage->getType(),
                        'type_label' => $this->getStorageTypeLabel($storage->getType()),
                        'is_default' => $storage->isDefault(),
                        'is_remote' => $storage->isRemote(),
                        'created_at' => $storage->getCreatedAt(),
                        'updated_at' => $storage->getUpdatedAt(),
                        'connection_latency' => $connectionResult->latencyMs ?? null,
                        'server_info' => $connectionResult->serverInfo ?? null,
                    ];
                    
                    if ($quota !== null) {
                        $usagePercentage = $quota->totalBytes > 0 
                            ? round(($quota->usedBytes / $quota->totalBytes) * 100, 2) 
                            : 0;
                        
                        $stats['total_space'] = $quota->totalBytes;
                        $stats['used_space'] = $quota->usedBytes;
                        $stats['free_space'] = $quota->totalBytes - $quota->usedBytes;
                        $stats['usage_percentage'] = $usagePercentage;
                        $stats['total_space_formatted'] = $this->formatBytes($quota->totalBytes);
                        $stats['used_space_formatted'] = $this->formatBytes($quota->usedBytes);
                        $stats['free_space_formatted'] = $this->formatBytes($quota->totalBytes - $quota->usedBytes);
                        
                        if ($usagePercentage > 95) {
                            $overallStatus = 'error';
                            $issues[] = "Хранилище '{$storage->getName()}' почти заполнено ({$usagePercentage}%)";
                        } elseif ($usagePercentage > 85) {
                            if ($overallStatus === 'healthy') {
                                $overallStatus = 'warning';
                            }
                            $issues[] = "Хранилище '{$storage->getName()}' заполнено на {$usagePercentage}%";
                        }
                    }
                    
                    if (!$connectionResult->success) {
                        $overallStatus = 'warning';
                        $stats['error'] = $connectionResult->errorMessage;
                        $issues[] = "Хранилище '{$storage->getName()}': " . $connectionResult->errorMessage;
                    }
                    
                    $storageStats[$storage->getName()] = $stats;
                } catch (\Exception $e) {
                    $overallStatus = 'warning';
                    $issues[] = "Хранилище '{$storage->getName()}': " . $e->getMessage();
                    $storageStats[$storage->getName()] = [
                        'id' => $storage->getId(),
                        'is_healthy' => false,
                        'error' => $e->getMessage(),
                        'type' => $storage->getType(),
                        'type_label' => $this->getStorageTypeLabel($storage->getType()),
                        'is_default' => $storage->isDefault(),
                        'is_remote' => $storage->isRemote(),
                    ];
                }
            }

            // Добавляем информацию о неактивных хранилищах
            $disabledStorages = $this->storageRepository->findBy(['isEnabled' => false]);
            $disabledCount = \count($disabledStorages);

            if (empty($storages)) {
                $issues[] = 'Нет активных хранилищ';
                $overallStatus = 'warning';
            }

            return [
                'status' => $overallStatus,
                'storages' => $storageStats,
                'total_active' => \count($storages),
                'total_disabled' => $disabledCount,
                'issues' => $issues,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'issues' => ['Ошибка проверки хранилищ: ' . $e->getMessage()],
            ];
        }
    }

    /**
     * Получить человекочитаемое название типа хранилища
     */
    private function getStorageTypeLabel(string $type): string
    {
        return match($type) {
            'local' => 'Локальное',
            'ftp' => 'FTP',
            'sftp' => 'SFTP',
            'http' => 'HTTP/WebDAV',
            default => $type,
        };
    }

    /**
     * Проверка дискового пространства
     */
    private function checkDiskSpace(): array
    {
        try {
            // Используем корневую директорию проекта
            $projectDir = dirname(__DIR__, 2);
            
            $paths = [
                'project' => $projectDir,
            ];

            $diskStats = [];
            $overallStatus = 'healthy';
            $issues = [];

            foreach ($paths as $name => $path) {
                // Проверяем существование пути
                if (!is_dir($path)) {
                    $diskStats[$name] = [
                        'path' => $path,
                        'error' => 'Директория не существует',
                    ];
                    continue;
                }

                $freeBytes = @disk_free_space($path);
                $totalBytes = @disk_total_space($path);

                if ($freeBytes !== false && $totalBytes !== false && $totalBytes > 0) {
                    $usedBytes = $totalBytes - $freeBytes;
                    $usagePercentage = round(($usedBytes / $totalBytes) * 100, 2);

                    $diskStats[$name] = [
                        'path' => $path,
                        'total' => $this->formatBytes((int) $totalBytes),
                        'free' => $this->formatBytes((int) $freeBytes),
                        'used' => $this->formatBytes((int) $usedBytes),
                        'usage_percentage' => $usagePercentage,
                    ];

                    if ($usagePercentage > 95) {
                        $overallStatus = 'error';
                        $issues[] = "Диск {$name} заполнен на {$usagePercentage}%";
                    } elseif ($usagePercentage > 85) {
                        if ($overallStatus === 'healthy') {
                            $overallStatus = 'warning';
                        }
                        $issues[] = "Диск {$name} заполнен на {$usagePercentage}%";
                    }
                } else {
                    $diskStats[$name] = [
                        'path' => $path,
                        'error' => 'Не удалось получить информацию о диске',
                    ];
                }
            }

            return [
                'status' => $overallStatus,
                'disks' => $diskStats,
                'issues' => $issues,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'warning',
                'error' => $e->getMessage(),
                'issues' => ['Ошибка проверки дискового пространства: ' . $e->getMessage()],
            ];
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $floatBytes = (float) $bytes;
        
        for ($i = 0; $floatBytes > 1024 && $i < \count($units) - 1; $i++) {
            $floatBytes /= 1024;
        }
        
        return round($floatBytes, 2) . ' ' . $units[$i];
    }

    /**
     * Проверка очереди обработки видео
     */
    private function checkProcessingQueue(): array
    {
        try {
            // Подсчитываем видео в разных статусах
            $stats = [
                'pending' => $this->videoRepository->count(['status' => 'draft']),
                'processing' => $this->videoRepository->count(['status' => 'processing']),
                'published' => $this->videoRepository->count(['status' => 'published']),
                'failed' => $this->videoRepository->count(['status' => 'rejected']),
            ];

            $status = 'healthy';
            $issues = [];

            // Проверяем на застрявшие задачи (в обработке более 2 часов)
            $stuckVideos = $this->entityManager->createQuery(
                'SELECT COUNT(v.id) FROM App\Entity\Video v 
                 WHERE v.status = :status 
                 AND v.updatedAt < :threshold'
            )
            ->setParameter('status', 'processing')
            ->setParameter('threshold', new \DateTime('-2 hours'))
            ->getSingleScalarResult();

            if ($stuckVideos > 0) {
                $status = 'warning';
                $issues[] = "Найдено {$stuckVideos} застрявших видео в обработке";
            }

            if ($stats['processing'] > 50) {
                $status = 'warning';
                $issues[] = "Много видео в очереди обработки: {$stats['processing']}";
            }

            if ($stats['failed'] > 20) {
                $status = 'warning';
                $issues[] = "Много неудачных обработок: {$stats['failed']}";
            }

            return [
                'status' => $status,
                'stats' => $stats,
                'stuck_videos' => $stuckVideos,
                'issues' => $issues,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'issues' => ['Ошибка проверки очереди: ' . $e->getMessage()],
            ];
        }
    }

    /**
     * Получить метрики производительности
     */
    public function getPerformanceMetrics(): array
    {
        try {
            return [
                'memory_usage' => [
                    'current' => memory_get_usage(true),
                    'peak' => memory_get_peak_usage(true),
                    'limit' => $this->convertToBytes(ini_get('memory_limit')),
                ],
                'database' => [
                    'total_videos' => $this->videoRepository->count([]),
                    'published_videos' => $this->videoRepository->count(['status' => 'published']),
                    'processing_videos' => $this->videoRepository->count(['status' => 'processing']),
                    'draft_videos' => $this->videoRepository->count(['status' => 'draft']),
                    'rejected_videos' => $this->videoRepository->count(['status' => 'rejected']),
                ],
                'cache' => [
                    'opcache_enabled' => \function_exists('opcache_get_status') && opcache_get_status() !== false,
                    'apcu_enabled' => \extension_loaded('apcu') && \ini_get('apc.enabled'),
                ],
                'php' => [
                    'version' => PHP_VERSION,
                    'max_execution_time' => ini_get('max_execution_time'),
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                    'post_max_size' => ini_get('post_max_size'),
                    'memory_limit' => ini_get('memory_limit'),
                ],
                'server' => [
                    'os' => PHP_OS,
                    'hostname' => gethostname(),
                    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get performance metrics', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Получить сводку по системе для отображения в заголовке
     */
    public function getSystemSummary(): array
    {
        $health = $this->getSystemHealth();
        
        $healthyCount = 0;
        $warningCount = 0;
        $errorCount = 0;
        
        foreach ($health['checks'] as $check) {
            match($check['status']) {
                'healthy' => $healthyCount++,
                'warning' => $warningCount++,
                'error' => $errorCount++,
                default => null,
            };
        }
        
        return [
            'overall_status' => $health['overall_status'],
            'healthy_checks' => $healthyCount,
            'warning_checks' => $warningCount,
            'error_checks' => $errorCount,
            'total_checks' => \count($health['checks']),
            'all_issues' => $this->collectAllIssues($health['checks']),
        ];
    }

    /**
     * Собрать все проблемы из проверок
     */
    private function collectAllIssues(array $checks): array
    {
        $allIssues = [];
        foreach ($checks as $checkName => $check) {
            if (!empty($check['issues'])) {
                foreach ($check['issues'] as $issue) {
                    $allIssues[] = [
                        'check' => $checkName,
                        'message' => $issue,
                        'status' => $check['status'],
                    ];
                }
            }
        }
        return $allIssues;
    }

    private function convertToBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '-1') {
            return -1;
        }
        
        $last = strtolower($value[\strlen($value) - 1]);
        $numValue = (int) $value;

        return match($last) {
            'g' => $numValue * 1024 * 1024 * 1024,
            'm' => $numValue * 1024 * 1024,
            'k' => $numValue * 1024,
            default => $numValue,
        };
    }
}