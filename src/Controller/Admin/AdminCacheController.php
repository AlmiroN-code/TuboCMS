<?php

namespace App\Controller\Admin;

use App\Service\AdminNotifierService;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;

#[Route('/admin/cache', name: 'admin_cache_')]
#[IsGranted('ROLE_ADMIN')]
class AdminCacheController extends AbstractController
{
    public function __construct(
        private CacheInterface $cache,
        private SettingsService $settings,
        private AdminNotifierService $notifier,
        private KernelInterface $kernel,
        private EntityManagerInterface $em,
        #[Autowire(service: 'doctrine.result_cache_pool')]
        private CacheItemPoolInterface $doctrineResultCache,
        #[Autowire(service: 'doctrine.system_cache_pool')]
        private CacheItemPoolInterface $doctrineSystemCache,
    ) {
    }

    /**
     * Быстрая очистка кэша из шапки админки (без CSRF для простоты)
     */
    #[Route('/quick-clear', name: 'quick_clear', methods: ['POST'])]
    public function quickClear(Request $request): Response
    {
        $referer = $request->headers->get('referer', $this->generateUrl('admin_dashboard'));
        
        try {
            // Очистка Symfony кэша
            $this->cache->clear();
            $this->settings->clearCache();
            
            // Очистка Doctrine кэша
            $this->clearDoctrineCache();
            
            $this->addFlash('success', 'Весь кэш очищен (включая Doctrine)');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Ошибка: ' . $e->getMessage());
        }

        return $this->redirect($referer);
    }
    
    /**
     * Очистка всех кэшей Doctrine
     */
    private function clearDoctrineCache(): void
    {
        // Очистка пулов кэша Doctrine напрямую
        $this->doctrineResultCache->clear();
        $this->doctrineSystemCache->clear();
        
        // Также очищаем через Configuration (на случай если есть другие кэши)
        $config = $this->em->getConfiguration();
        
        // Очистка кэша метаданных
        $metadataCache = $config->getMetadataCache();
        if ($metadataCache) {
            $metadataCache->clear();
        }
        
        // Очистка кэша запросов
        $queryCache = $config->getQueryCache();
        if ($queryCache) {
            $queryCache->clear();
        }
        
        // Очистка кэша результатов
        $resultCache = $config->getResultCache();
        if ($resultCache) {
            $resultCache->clear();
        }
        
        // Очистка identity map (отсоединение всех сущностей)
        $this->em->clear();
    }

    #[Route('', name: 'index')]
    public function index(): Response
    {
        $cacheDir = $this->kernel->getCacheDir();
        $cacheSize = $this->getDirectorySize($cacheDir);
        
        $pools = [
            'cache.app' => 'Основной кэш приложения',
            'doctrine.result_cache_pool' => 'Кэш результатов Doctrine',
            'doctrine.system_cache_pool' => 'Системный кэш Doctrine',
        ];
        
        return $this->render('admin/cache/index.html.twig', [
            'cache_size' => $this->formatBytes($cacheSize),
            'cache_dir' => $cacheDir,
            'pools' => $pools,
            'environment' => $this->kernel->getEnvironment(),
        ]);
    }

    #[Route('/clear/{pool}', name: 'clear', methods: ['POST'])]
    public function clearPool(Request $request, string $pool = 'all'): Response
    {
        if (!$this->isCsrfTokenValid('cache_clear', $request->request->get('_token'))) {
            $this->addFlash('error', 'Неверный CSRF токен');
            return $this->redirectToRoute('admin_cache_index');
        }

        try {
            if ($pool === 'all') {
                // Очистка всего кэша
                $this->cache->clear();
                $this->settings->clearCache();
                $this->clearDoctrineCache();
                $this->addFlash('success', 'Весь кэш успешно очищен (включая Doctrine)');
            } elseif ($pool === 'settings') {
                $this->settings->clearCache();
                $this->addFlash('success', 'Кэш настроек очищен');
            } elseif ($pool === 'doctrine') {
                $this->clearDoctrineCache();
                $this->addFlash('success', 'Кэш Doctrine очищен');
            } else {
                // Очистка конкретного пула через команду
                $this->cache->clear();
                $this->addFlash('success', "Кэш пула '$pool' очищен");
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Ошибка очистки кэша: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_cache_index');
    }

    #[Route('/warmup', name: 'warmup', methods: ['POST'])]
    public function warmup(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('cache_warmup', $request->request->get('_token'))) {
            $this->addFlash('error', 'Неверный CSRF токен');
            return $this->redirectToRoute('admin_cache_index');
        }

        try {
            // Прогрев кэша настроек
            $this->settings->clearCache();
            $this->settings->get('site_name'); // Триггерит загрузку всех настроек
            
            $this->addFlash('success', 'Кэш прогрет успешно');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Ошибка прогрева кэша: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_cache_index');
    }

    private function getDirectorySize(string $path): int
    {
        $size = 0;
        if (!is_dir($path)) {
            return $size;
        }

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $size += $file->getSize();
        }

        return $size;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
