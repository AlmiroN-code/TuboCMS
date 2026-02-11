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
        
        $stats = $this->statsService->getDashboardStats();
        
        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
            'recent_videos' => $this->videoRepository->findBy([], ['createdAt' => 'DESC'], 5),
            'storage_stats' => $storageStats,
            'recent_errors' => $stats['recent_errors'] ?? [],
            'recent_activity' => $this->statsService->getRecentUserActivity(),
        ]);
    }

    #[Route('/test-notifications', name: 'admin_test_notifications')]
    public function testNotifications(): Response
    {
        $this->addFlash('success', 'Тестовое успешное уведомление!');
        $this->addFlash('error', 'Тестовое уведомление об ошибке!');
        $this->addFlash('warning', 'Тестовое предупреждение!');
        $this->addFlash('info', 'Тестовое информационное сообщение!');
        
        return $this->redirectToRoute('admin_dashboard');
    }

    /**
     * Получить статистику хранилищ с информацией о квотах.
     * 
     * Requirements 6.1, 6.2: Display storage usage and quota information
     * 
     * ВАЖНО: Квоты удалённых хранилищ НЕ загружаются синхронно,
     * так как это может занять много времени (FTP/WebDAV соединения).
     * Квоты можно загрузить через AJAX на странице Storage.
     */
    private function getStorageStatsWithQuotas(): array
    {
        $allStats = $this->storageStatsService->getAllStoragesStats();
        $result = [];

        foreach ($allStats as $key => $stat) {
            // НЕ загружаем квоты удалённых хранилищ синхронно - это медленно!
            // Квоты можно загрузить через отдельный AJAX-запрос
            $result[$key] = [
                'storage' => $stat['storage'],
                'name' => $stat['name'],
                'type' => $stat['type'],
                'filesCount' => $stat['filesCount'],
                'totalSize' => $stat['totalSize'],
                'totalSizeFormatted' => $this->storageStatsService->formatSize($stat['totalSize']),
                'quota' => null,
                'usagePercent' => null,
                'warningExceeded' => false,
                'quotaUsedFormatted' => null,
                'quotaTotalFormatted' => null,
                'quotaAvailableFormatted' => null,
            ];
        }

        return $result;
    }
}
