<?php

namespace App\Controller\Admin;

use App\Service\StorageManager;
use App\Service\StorageStatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private \App\Service\StatsService $statsService,
        private \App\Repository\VideoRepository $videoRepository,
        private StorageStatsService $storageStatsService,
        private StorageManager $storageManager,
    ) {
    }

    #[Route('', name: 'admin_dashboard')]
    public function dashboard(): Response
    {
        // Получаем статистику хранилищ с информацией о квотах
        $storageStats = $this->getStorageStatsWithQuotas();
        
        return $this->render('admin/dashboard.html.twig', [
            'stats' => $this->statsService->getDashboardStats(),
            'recent_videos' => $this->videoRepository->findBy([], ['createdAt' => 'DESC'], 5),
            'storage_stats' => $storageStats,
        ]);
    }

    /**
     * Получить статистику хранилищ с информацией о квотах.
     * 
     * Requirements 6.1, 6.2: Display storage usage and quota information
     */
    private function getStorageStatsWithQuotas(): array
    {
        $allStats = $this->storageStatsService->getAllStoragesStats();
        $result = [];

        foreach ($allStats as $key => $stat) {
            $quota = null;
            $usagePercent = null;
            $warningExceeded = false;

            // Получаем квоту для удалённых хранилищ
            if ($stat['storage'] !== null) {
                try {
                    $adapter = $this->storageManager->getAdapter($stat['storage']);
                    $quota = $adapter->getQuota();
                    
                    if ($quota !== null) {
                        $usagePercent = $quota->getUsagePercent();
                        $warningExceeded = $quota->isWarningThresholdExceeded();
                    }
                } catch (\Throwable $e) {
                    // Игнорируем ошибки получения квоты
                }
            }

            $result[$key] = [
                'storage' => $stat['storage'],
                'name' => $stat['name'],
                'type' => $stat['type'],
                'filesCount' => $stat['filesCount'],
                'totalSize' => $stat['totalSize'],
                'totalSizeFormatted' => $this->storageStatsService->formatSize($stat['totalSize']),
                'quota' => $quota,
                'usagePercent' => $usagePercent,
                'warningExceeded' => $warningExceeded,
                'quotaUsedFormatted' => $quota ? $this->storageStatsService->formatSize($quota->usedBytes) : null,
                'quotaTotalFormatted' => $quota && $quota->totalBytes ? $this->storageStatsService->formatSize($quota->totalBytes) : null,
                'quotaAvailableFormatted' => $quota && $quota->getAvailableBytes() !== null ? $this->storageStatsService->formatSize($quota->getAvailableBytes()) : null,
            ];
        }

        return $result;
    }
}
