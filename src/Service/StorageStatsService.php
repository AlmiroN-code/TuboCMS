<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Storage;
use App\Repository\StorageRepository;
use App\Storage\DTO\StorageQuota;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Сервис для расчёта статистики использования хранилищ.
 * 
 * Validates: Requirements 6.1, 6.3
 */
class StorageStatsService
{
    /**
     * Порог предупреждения об использовании хранилища (80%).
     * Requirements 6.3: WHEN storage usage exceeds 80% THEN the System SHALL display a warning notification
     */
    public const WARNING_THRESHOLD_PERCENT = 80.0;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StorageRepository $storageRepository,
    ) {
    }

    /**
     * Получить статистику для конкретного хранилища.
     * 
     * Requirement 6.1: WHEN viewing storage dashboard THEN the System SHALL 
     * display total files count and estimated size per storage
     * 
     * @param Storage $storage Хранилище
     * @return array{filesCount: int, totalSize: int}
     */
    public function getStorageStats(Storage $storage): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        $qb->select('COUNT(vf.id) as filesCount, COALESCE(SUM(vf.fileSize), 0) as totalSize')
           ->from(\App\Entity\VideoFile::class, 'vf')
           ->where('vf.storage = :storage')
           ->setParameter('storage', $storage);
        
        $result = $qb->getQuery()->getSingleResult();
        
        return [
            'filesCount' => (int) $result['filesCount'],
            'totalSize' => (int) $result['totalSize'],
        ];
    }

    /**
     * Получить статистику для локального хранилища (файлы без привязки к Storage).
     * 
     * @return array{filesCount: int, totalSize: int}
     */
    public function getLocalStorageStats(): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        $qb->select('COUNT(vf.id) as filesCount, COALESCE(SUM(vf.fileSize), 0) as totalSize')
           ->from(\App\Entity\VideoFile::class, 'vf')
           ->where('vf.storage IS NULL');
        
        $result = $qb->getQuery()->getSingleResult();
        
        return [
            'filesCount' => (int) $result['filesCount'],
            'totalSize' => (int) $result['totalSize'],
        ];
    }

    /**
     * Получить статистику для всех хранилищ.
     * 
     * @return array<int|string, array{storage: Storage|null, name: string, type: string, filesCount: int, totalSize: int}>
     */
    public function getAllStoragesStats(): array
    {
        $stats = [];
        
        // Статистика локального хранилища
        $localStats = $this->getLocalStorageStats();
        $stats['local'] = [
            'storage' => null,
            'name' => 'Локальное хранилище',
            'type' => 'local',
            'filesCount' => $localStats['filesCount'],
            'totalSize' => $localStats['totalSize'],
        ];
        
        // Статистика всех удалённых хранилищ
        $storages = $this->storageRepository->findAll();
        
        foreach ($storages as $storage) {
            $storageStats = $this->getStorageStats($storage);
            $stats[$storage->getId()] = [
                'storage' => $storage,
                'name' => $storage->getName(),
                'type' => $storage->getType(),
                'filesCount' => $storageStats['filesCount'],
                'totalSize' => $storageStats['totalSize'],
            ];
        }
        
        return $stats;
    }

    /**
     * Получить общую статистику по всем хранилищам.
     * 
     * @return array{totalFilesCount: int, totalSize: int, storagesCount: int}
     */
    public function getTotalStats(): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        $qb->select('COUNT(vf.id) as filesCount, COALESCE(SUM(vf.fileSize), 0) as totalSize')
           ->from(\App\Entity\VideoFile::class, 'vf');
        
        $result = $qb->getQuery()->getSingleResult();
        
        $storagesCount = $this->storageRepository->count([]);
        
        return [
            'totalFilesCount' => (int) $result['filesCount'],
            'totalSize' => (int) $result['totalSize'],
            'storagesCount' => $storagesCount + 1, // +1 для локального хранилища
        ];
    }

    /**
     * Форматировать размер в человекочитаемый формат.
     * 
     * @param int $bytes Размер в байтах
     * @return string Форматированный размер (например, "1.5 GB")
     */
    public function formatSize(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = (int) floor(log($bytes, 1024));
        $factor = min($factor, \count($units) - 1);
        
        return \sprintf('%.2f %s', $bytes / (1024 ** $factor), $units[$factor]);
    }

    /**
     * Проверить, превышен ли порог предупреждения для квоты.
     * 
     * Requirements 6.3: WHEN storage usage exceeds 80% THEN the System SHALL 
     * display a warning notification
     * 
     * @param StorageQuota|null $quota Информация о квоте
     * @return bool True если использование >= 80%
     */
    public function isWarningThresholdExceeded(?StorageQuota $quota): bool
    {
        if ($quota === null) {
            return false;
        }

        return $quota->isWarningThresholdExceeded();
    }

    /**
     * Получить список хранилищ с превышенным порогом предупреждения.
     * 
     * Requirements 6.3: WHEN storage usage exceeds 80% THEN the System SHALL 
     * display a warning notification
     * 
     * @param array<int|string, array{quota: StorageQuota|null}> $storageStatsWithQuotas Статистика с квотами
     * @return array<int|string, array{name: string, usagePercent: float}> Хранилища с превышением
     */
    public function getStoragesWithWarning(array $storageStatsWithQuotas): array
    {
        $warnings = [];

        foreach ($storageStatsWithQuotas as $key => $stat) {
            if (isset($stat['quota']) && $stat['quota'] instanceof StorageQuota) {
                $usagePercent = $stat['quota']->getUsagePercent();
                if ($usagePercent !== null && $usagePercent >= self::WARNING_THRESHOLD_PERCENT) {
                    $warnings[$key] = [
                        'name' => $stat['name'] ?? 'Unknown',
                        'usagePercent' => $usagePercent,
                    ];
                }
            }
        }

        return $warnings;
    }
}
