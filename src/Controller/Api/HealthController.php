<?php

namespace App\Controller\Api;

use App\Service\SystemMonitoringService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/health')]
class HealthController extends AbstractController
{
    public function __construct(
        private SystemMonitoringService $monitoringService
    ) {
    }

    #[Route('/check', name: 'api_health_check', methods: ['GET'])]
    public function healthCheck(): JsonResponse
    {
        $health = $this->monitoringService->getSystemHealth();
        
        $statusCode = match ($health['status']) {
            'healthy' => Response::HTTP_OK,
            'warning' => Response::HTTP_OK,
            'unhealthy' => Response::HTTP_SERVICE_UNAVAILABLE,
            default => Response::HTTP_INTERNAL_SERVER_ERROR
        };

        return new JsonResponse($health, $statusCode);
    }

    #[Route('/stats', name: 'api_health_stats', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function systemStats(): JsonResponse
    {
        $stats = $this->monitoringService->getSystemStats();
        return new JsonResponse($stats);
    }

    #[Route('/ping', name: 'api_health_ping', methods: ['GET'])]
    public function ping(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'timestamp' => new \DateTimeImmutable(),
            'version' => '1.0.0'
        ]);
    }
}