<?php

namespace App\Controller\Admin;

use App\Repository\CategoryRepository;
use App\Repository\TagRepository;
use App\Repository\ModelProfileRepository;
use App\Service\SystemMonitoringService;
use App\Service\VideoProcessingService;
use App\Service\StorageManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/system')]
class AdminSystemController extends AbstractController
{
    public function __construct(
        private readonly SystemMonitoringService $monitoringService,
        private readonly VideoProcessingService $videoProcessingService,
        private readonly StorageManager $storageManager,
        private readonly CategoryRepository $categoryRepository,
        private readonly TagRepository $tagRepository,
        private readonly ModelProfileRepository $modelRepository,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    #[Route('/', name: 'admin_system_dashboard')]
    public function dashboard(): Response
    {
        try {
            $systemHealth = $this->monitoringService->getSystemHealth();
            $performanceMetrics = $this->monitoringService->getPerformanceMetrics();
            $systemSummary = $this->monitoringService->getSystemSummary();

            return $this->render('admin/system/dashboard.html.twig', [
                'system_health' => $systemHealth,
                'performance_metrics' => $performanceMetrics,
                'system_summary' => $systemSummary,
            ]);
        } catch (\Exception $e) {
            return new Response('
                <h1>Ошибка мониторинга</h1>
                <p><strong>Сообщение:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>
                <p><strong>Файл:</strong> ' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</p>
                <pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>
                <a href="/admin">← Назад в админку</a>
            ');
        }
    }

    #[Route('/health', name: 'admin_system_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        $health = $this->monitoringService->getSystemHealth();
        
        return $this->json($health);
    }

    #[Route('/metrics', name: 'admin_system_metrics', methods: ['GET'])]
    public function metrics(): JsonResponse
    {
        $metrics = $this->monitoringService->getPerformanceMetrics();
        
        return $this->json($metrics);
    }

    #[Route('/ffmpeg-info', name: 'admin_system_ffmpeg_info', methods: ['GET'])]
    public function ffmpegInfo(): JsonResponse
    {
        $info = [
            'available' => $this->videoProcessingService->isFFmpegAvailable(),
            'capabilities' => $this->videoProcessingService->getCapabilities(),
            'requirements' => $this->videoProcessingService->checkSystemRequirements(),
        ];

        return $this->json($info);
    }

    #[Route('/clear-cache', name: 'admin_system_clear_cache', methods: ['POST'])]
    public function clearCache(): JsonResponse
    {
        try {
            // Очищаем кэш адаптеров хранилища
            $this->storageManager->clearAdapterCache();
            
            // Пересчитываем счётчики видео для категорий, тегов и моделей
            $categoriesUpdated = $this->categoryRepository->recalculateVideoCounts();
            $tagsUpdated = $this->tagRepository->recalculateVideoCounts();
            $modelsUpdated = $this->modelRepository->recalculateVideoCounts();
            
            $this->addFlash('success', 'Кэш системы очищен, счётчики обновлены');
            
            return $this->json([
                'success' => true,
                'message' => 'Кэш успешно очищен',
                'counters_updated' => [
                    'categories' => $categoriesUpdated,
                    'tags' => $tagsUpdated,
                    'models' => $modelsUpdated,
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/test-ffmpeg', name: 'admin_system_test_ffmpeg', methods: ['POST'])]
    public function testFFmpeg(): JsonResponse
    {
        try {
            $available = $this->videoProcessingService->isFFmpegAvailable();
            $requirements = $this->videoProcessingService->checkSystemRequirements();
            
            if (!$available) {
                return $this->json([
                    'success' => false,
                    'error' => 'FFmpeg недоступен'
                ]);
            }

            // Создаем тестовое видео (1 секунда черного экрана)
            $tempDir = sys_get_temp_dir();
            $testVideo = $tempDir . '/test_video_' . uniqid() . '.mp4';
            $testPoster = $tempDir . '/test_poster_' . uniqid() . '.jpg';

            // Генерируем тестовое видео
            $testVideoInfo = $this->videoProcessingService->processVideo($testVideo, $tempDir);

            // Очищаем временные файлы
            if (file_exists($testVideo)) {
                unlink($testVideo);
            }
            if (file_exists($testPoster)) {
                unlink($testPoster);
            }

            return $this->json([
                'success' => true,
                'available' => $available,
                'requirements' => $requirements,
                'test_result' => $testVideoInfo
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/run-monitor', name: 'admin_system_run_monitor', methods: ['POST'])]
    public function runMonitor(): JsonResponse
    {
        try {
            $phpPath = 'D:\\laragon\\bin\\php\\php-8.4.15-nts-Win32-vs17-x64\\php.exe';
            $consolePath = $this->projectDir . '/bin/console';
            
            if (!file_exists($phpPath)) {
                return $this->json([
                    'success' => false,
                    'error' => 'PHP не найден по пути: ' . $phpPath
                ], 500);
            }
            
            if (!file_exists($consolePath)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Console не найден по пути: ' . $consolePath
                ], 500);
            }
            
            $command = sprintf('cd /d "%s" && "%s" bin/console app:monitor-performance --table-stats 2>&1', $this->projectDir, $phpPath);
            $output = shell_exec($command);
            
            if ($output === null) {
                return $this->json([
                    'success' => false,
                    'error' => 'Не удалось выполнить команду. Проверьте права доступа.'
                ], 500);
            }
            
            // Конвертируем из Windows-1251 в UTF-8
            if (function_exists('mb_convert_encoding')) {
                $output = mb_convert_encoding($output, 'UTF-8', 'Windows-1251');
            }
            
            return $this->json([
                'success' => true,
                'output' => $output ?: 'Команда выполнена, но вывод пуст'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/run-performance-test', name: 'admin_system_run_performance_test', methods: ['POST'])]
    public function runPerformanceTest(): JsonResponse
    {
        try {
            $phpPath = 'D:\\laragon\\bin\\php\\php-8.4.15-nts-Win32-vs17-x64\\php.exe';
            $consolePath = $this->projectDir . '/bin/console';
            
            if (!file_exists($phpPath)) {
                return $this->json([
                    'success' => false,
                    'error' => 'PHP не найден по пути: ' . $phpPath
                ], 500);
            }
            
            if (!file_exists($consolePath)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Console не найден по пути: ' . $consolePath
                ], 500);
            }
            
            $command = sprintf('cd /d "%s" && "%s" bin/console app:test-performance --iterations=3 2>&1', $this->projectDir, $phpPath);
            $output = shell_exec($command);
            
            if ($output === null) {
                return $this->json([
                    'success' => false,
                    'error' => 'Не удалось выполнить команду. Проверьте права доступа.'
                ], 500);
            }
            
            // Конвертируем из Windows-1251 в UTF-8
            if (function_exists('mb_convert_encoding')) {
                $output = mb_convert_encoding($output, 'UTF-8', 'Windows-1251');
            }
            
            return $this->json([
                'success' => true,
                'output' => $output ?: 'Команда выполнена, но вывод пуст'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/clear-optimized-cache', name: 'admin_system_clear_optimized_cache', methods: ['POST'])]
    public function clearOptimizedCache(): JsonResponse
    {
        try {
            $phpPath = 'D:\\laragon\\bin\\php\\php-8.4.15-nts-Win32-vs17-x64\\php.exe';
            $consolePath = $this->projectDir . '/bin/console';
            
            if (!file_exists($phpPath)) {
                return $this->json([
                    'success' => false,
                    'error' => 'PHP не найден по пути: ' . $phpPath
                ], 500);
            }
            
            if (!file_exists($consolePath)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Console не найден по пути: ' . $consolePath
                ], 500);
            }
            
            $command = sprintf('cd /d "%s" && "%s" bin/console app:cache:clear-optimized --all 2>&1', $this->projectDir, $phpPath);
            $output = shell_exec($command);
            
            if ($output === null) {
                return $this->json([
                    'success' => false,
                    'error' => 'Не удалось выполнить команду. Проверьте права доступа.'
                ], 500);
            }
            
            // Конвертируем из Windows-1251 в UTF-8
            if (function_exists('mb_convert_encoding')) {
                $output = mb_convert_encoding($output, 'UTF-8', 'Windows-1251');
            }
            
            $this->addFlash('success', 'Оптимизированный кэш успешно очищен');
            
            return $this->json([
                'success' => true,
                'output' => $output ?: 'Команда выполнена, но вывод пуст'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}