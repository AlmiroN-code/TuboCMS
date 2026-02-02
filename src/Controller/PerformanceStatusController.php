<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\CacheOptimizationService;
use App\Service\CircuitBreaker\CircuitBreakerFactory;
use App\Service\MaterializedViewService;
use App\Service\OptimizedQueryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class PerformanceStatusController extends AbstractController
{
    public function __construct(
        private readonly OptimizedQueryService $optimizedQueryService,
        private readonly MaterializedViewService $materializedViewService,
        private readonly CircuitBreakerFactory $circuitBreakerFactory,
        private readonly CacheOptimizationService $cacheOptimizationService
    ) {}

    #[Route('/performance-status', name: 'performance_status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        $status = [
            'redis_connection' => $this->testRedisConnection(),
            'materialized_views' => $this->testMaterializedViews(),
            'circuit_breaker' => $this->testCircuitBreaker(),
            'cache_optimization' => $this->testCacheOptimization(),
            'optimized_queries' => $this->testOptimizedQueries()
        ];

        return $this->json([
            'status' => 'success',
            'performance_optimizations' => $status,
            'timestamp' => new \DateTimeImmutable()
        ]);
    }

    private function testRedisConnection(): array
    {
        try {
            $this->cacheOptimizationService->warmupCache('test_key', 'test_value', 60);
            return ['status' => 'working', 'message' => 'Redis подключение работает'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function testMaterializedViews(): array
    {
        try {
            $this->materializedViewService->refreshAllViews();
            return [
                'status' => 'working',
                'message' => 'Материализованные представления работают'
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function testCircuitBreaker(): array
    {
        try {
            $breaker = $this->circuitBreakerFactory->create('test_service');
            return [
                'status' => 'working',
                'message' => 'Circuit Breaker инициализирован',
                'state' => $breaker->getState()
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function testCacheOptimization(): array
    {
        try {
            $this->cacheOptimizationService->warmupCache('performance_test', ['data' => 'test'], 300);
            return [
                'status' => 'working',
                'message' => 'Кеш оптимизация работает'
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function testOptimizedQueries(): array
    {
        try {
            $videos = $this->optimizedQueryService->getPopularVideos(5);
            return [
                'status' => 'working',
                'message' => 'Оптимизированные запросы работают',
                'sample_count' => count($videos)
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}