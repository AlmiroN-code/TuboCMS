<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Psr\Log\LoggerInterface;

class SystemMonitoringService
{
    public function __construct(
        private EntityManagerInterface $em,
        private VideoProcessingService $videoProcessor,
        private LoggerInterface $logger
    ) {
    }

    public function getSystemHealth(): array
    {
        $health = [
            'status' => 'healthy',
            'checks' => [],
            'timestamp' => new \DateTimeImmutable()
        ];

        // Проверка подключения к базе данных
        $health['checks']['database'] = $this->checkDatabase();
        
        // Проверка доступности FFmpeg
        $health['checks']['ffmpeg'] = $this->checkFFmpeg();
        
        // Проверка дискового пространства
        $health['checks']['disk_space'] = $this->checkDiskSpace();
        
        // Проверка очереди сообщений
        $health['checks']['message_queue'] = $this->checkMessageQueue();

        // Определяем общий статус
        foreach ($health['checks'] as $check) {
            if ($check['status'] !== 'healthy') {
                $health['status'] = 'unhealthy';
                break;
            }
        }

        return $health;
    }

    private function checkDatabase(): array
    {
        try {
            $this->em->getConnection()->executeQuery('SELECT 1');
            return [
                'status' => 'healthy',
                'message' => 'Database connection is working'
            ];
        } catch (\Exception $e) {
            $this->logger->error('Database health check failed', ['error' => $e->getMessage()]);
            return [
                'status' => 'unhealthy',
                'message' => 'Database connection failed: ' . $e->getMessage()
            ];
        }
    }

    private function checkFFmpeg(): array
    {
        try {
            if ($this->videoProcessor->isFFmpegAvailable()) {
                return [
                    'status' => 'healthy',
                    'message' => 'FFmpeg is available'
                ];
            } else {
                return [
                    'status' => 'unhealthy',
                    'message' => 'FFmpeg is not available'
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error('FFmpeg health check failed', ['error' => $e->getMessage()]);
            return [
                'status' => 'unhealthy',
                'message' => 'FFmpeg check failed: ' . $e->getMessage()
            ];
        }
    }

    private function checkDiskSpace(): array
    {
        try {
            $mediaPath = $_SERVER['DOCUMENT_ROOT'] . '/media';
            $freeBytes = disk_free_space($mediaPath);
            $totalBytes = disk_total_space($mediaPath);
            
            if ($freeBytes === false || $totalBytes === false) {
                return [
                    'status' => 'unknown',
                    'message' => 'Could not determine disk space'
                ];
            }

            $freePercentage = ($freeBytes / $totalBytes) * 100;
            $freeGB = round($freeBytes / (1024 * 1024 * 1024), 2);

            if ($freePercentage < 10) {
                return [
                    'status' => 'unhealthy',
                    'message' => "Low disk space: {$freeGB}GB free ({$freePercentage}%)"
                ];
            } elseif ($freePercentage < 20) {
                return [
                    'status' => 'warning',
                    'message' => "Disk space getting low: {$freeGB}GB free ({$freePercentage}%)"
                ];
            } else {
                return [
                    'status' => 'healthy',
                    'message' => "Sufficient disk space: {$freeGB}GB free ({$freePercentage}%)"
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error('Disk space health check failed', ['error' => $e->getMessage()]);
            return [
                'status' => 'unknown',
                'message' => 'Disk space check failed: ' . $e->getMessage()
            ];
        }
    }

    private function checkMessageQueue(): array
    {
        try {
            // Проверяем количество видео в обработке
            $processingCount = $this->em->createQueryBuilder()
                ->select('COUNT(v.id)')
                ->from('App\Entity\Video', 'v')
                ->where('v.processingStatus IN (:statuses)')
                ->setParameter('statuses', ['pending', 'processing'])
                ->getQuery()
                ->getSingleScalarResult();

            if ($processingCount > 50) {
                return [
                    'status' => 'warning',
                    'message' => "High queue load: {$processingCount} videos in processing"
                ];
            } else {
                return [
                    'status' => 'healthy',
                    'message' => "Queue is healthy: {$processingCount} videos in processing"
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error('Message queue health check failed', ['error' => $e->getMessage()]);
            return [
                'status' => 'unhealthy',
                'message' => 'Message queue check failed: ' . $e->getMessage()
            ];
        }
    }

    public function getSystemStats(): array
    {
        try {
            return [
                'videos' => [
                    'total' => $this->getVideoCount(),
                    'published' => $this->getVideoCount('published'),
                    'processing' => $this->getVideoCount('processing'),
                    'failed' => $this->getFailedVideoCount()
                ],
                'users' => [
                    'total' => $this->getUserCount(),
                    'verified' => $this->getUserCount(true),
                    'premium' => $this->getPremiumUserCount()
                ],
                'storage' => $this->getStorageStats(),
                'performance' => $this->getPerformanceStats()
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get system stats', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function getVideoCount(?string $status = null): int
    {
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(v.id)')
            ->from('App\Entity\Video', 'v');

        if ($status) {
            $qb->where('v.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    private function getFailedVideoCount(): int
    {
        return $this->em->createQueryBuilder()
            ->select('COUNT(v.id)')
            ->from('App\Entity\Video', 'v')
            ->where('v.processingStatus = :status')
            ->setParameter('status', 'error')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function getUserCount(?bool $verified = null): int
    {
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from('App\Entity\User', 'u');

        if ($verified !== null) {
            $qb->where('u.isVerified = :verified')
               ->setParameter('verified', $verified);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    private function getPremiumUserCount(): int
    {
        return $this->em->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from('App\Entity\User', 'u')
            ->where('u.isPremium = :premium')
            ->setParameter('premium', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function getStorageStats(): array
    {
        $mediaPath = $_SERVER['DOCUMENT_ROOT'] . '/media';
        
        return [
            'total_space' => disk_total_space($mediaPath),
            'free_space' => disk_free_space($mediaPath),
            'used_space' => disk_total_space($mediaPath) - disk_free_space($mediaPath)
        ];
    }

    private function getPerformanceStats(): array
    {
        return [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'php_version' => PHP_VERSION,
            'uptime' => $this->getUptime()
        ];
    }

    private function getUptime(): ?int
    {
        if (function_exists('sys_getloadavg')) {
            return sys_getloadavg()[0] ?? null;
        }
        return null;
    }
}