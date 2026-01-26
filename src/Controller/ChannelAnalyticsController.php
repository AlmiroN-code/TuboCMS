<?php

namespace App\Controller;

use App\Repository\ChannelRepository;
use App\Service\ChannelAnalyticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/channel/{slug}/analytics')]
#[IsGranted('ROLE_USER')]
class ChannelAnalyticsController extends AbstractController
{
    public function __construct(
        private ChannelRepository $channelRepository,
        private ChannelAnalyticsService $analyticsService
    ) {}

    #[Route('', name: 'channel_analytics_dashboard')]
    public function dashboard(string $slug, Request $request): Response
    {
        $channel = $this->channelRepository->findBySlug($slug);
        
        if (!$channel) {
            throw $this->createNotFoundException('Канал не найден');
        }

        // Проверяем права доступа
        if ($channel->getOwner() !== $this->getUser() && !in_array('ROLE_ADMIN', $this->getUser()->getRoles())) {
            throw $this->createAccessDeniedException('У вас нет доступа к аналитике этого канала');
        }

        $days = $request->query->getInt('days', 30);
        $allowedPeriods = [7, 14, 30, 60, 90, 365];
        
        if (!in_array($days, $allowedPeriods)) {
            $days = 30;
        }

        $dashboard = $this->analyticsService->getChannelDashboard($channel, $days);
        $comparison = $this->analyticsService->getComparativeAnalytics($channel, $days);

        return $this->render('channel/analytics/dashboard.html.twig', [
            'channel' => $channel,
            'dashboard' => $dashboard,
            'comparison' => $comparison,
            'selectedPeriod' => $days,
            'allowedPeriods' => $allowedPeriods,
        ]);
    }

    #[Route('/views-chart', name: 'channel_analytics_views_chart')]
    public function viewsChart(string $slug, Request $request): JsonResponse
    {
        $channel = $this->channelRepository->findBySlug($slug);
        
        if (!$channel) {
            return new JsonResponse(['error' => 'Канал не найден'], 404);
        }

        if ($channel->getOwner() !== $this->getUser() && !in_array('ROLE_ADMIN', $this->getUser()->getRoles())) {
            return new JsonResponse(['error' => 'Доступ запрещен'], 403);
        }

        $days = $request->query->getInt('days', 30);
        $dashboard = $this->analyticsService->getChannelDashboard($channel, $days);

        return new JsonResponse([
            'viewsChart' => $dashboard['charts']['views'],
            'subscribersChart' => $dashboard['charts']['subscribers'],
        ]);
    }

    #[Route('/demographics', name: 'channel_analytics_demographics')]
    public function demographics(string $slug, Request $request): Response
    {
        $channel = $this->channelRepository->findBySlug($slug);
        
        if (!$channel) {
            throw $this->createNotFoundException('Канал не найден');
        }

        if ($channel->getOwner() !== $this->getUser() && !in_array('ROLE_ADMIN', $this->getUser()->getRoles())) {
            throw $this->createAccessDeniedException('У вас нет доступа к аналитике этого канала');
        }

        $days = $request->query->getInt('days', 30);
        $dashboard = $this->analyticsService->getChannelDashboard($channel, $days);

        return $this->render('channel/analytics/demographics.html.twig', [
            'channel' => $channel,
            'demographics' => $dashboard['demographics'],
            'selectedPeriod' => $days,
        ]);
    }

    #[Route('/revenue', name: 'channel_analytics_revenue')]
    public function revenue(string $slug, Request $request): Response
    {
        $channel = $this->channelRepository->findBySlug($slug);
        
        if (!$channel) {
            throw $this->createNotFoundException('Канал не найден');
        }

        if ($channel->getOwner() !== $this->getUser() && !in_array('ROLE_ADMIN', $this->getUser()->getRoles())) {
            throw $this->createAccessDeniedException('У вас нет доступа к аналитике этого канала');
        }

        $days = $request->query->getInt('days', 30);
        $dashboard = $this->analyticsService->getChannelDashboard($channel, $days);

        return $this->render('channel/analytics/revenue.html.twig', [
            'channel' => $channel,
            'donations' => $dashboard['donations'],
            'summary' => $dashboard['summary'],
            'selectedPeriod' => $days,
        ]);
    }

    #[Route('/export', name: 'channel_analytics_export')]
    public function export(string $slug, Request $request): Response
    {
        $channel = $this->channelRepository->findBySlug($slug);
        
        if (!$channel) {
            throw $this->createNotFoundException('Канал не найден');
        }

        if ($channel->getOwner() !== $this->getUser() && !in_array('ROLE_ADMIN', $this->getUser()->getRoles())) {
            throw $this->createAccessDeniedException('У вас нет доступа к аналитике этого канала');
        }

        $startDate = new \DateTime($request->query->get('start_date', '-30 days'));
        $endDate = new \DateTime($request->query->get('end_date', 'today'));

        $csv = $this->analyticsService->exportAnalytics($channel, $startDate, $endDate);

        $filename = sprintf(
            'analytics_%s_%s_%s.csv',
            $channel->getSlug(),
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    #[Route('/real-time', name: 'channel_analytics_realtime')]
    public function realTime(string $slug): Response
    {
        $channel = $this->channelRepository->findBySlug($slug);
        
        if (!$channel) {
            throw $this->createNotFoundException('Канал не найден');
        }

        if ($channel->getOwner() !== $this->getUser() && !in_array('ROLE_ADMIN', $this->getUser()->getRoles())) {
            throw $this->createAccessDeniedException('У вас нет доступа к аналитике этого канала');
        }

        // Получаем данные за сегодня
        $today = new \DateTime('today');
        $dashboard = $this->analyticsService->getChannelDashboard($channel, 1);

        return $this->render('channel/analytics/realtime.html.twig', [
            'channel' => $channel,
            'todayStats' => $dashboard['summary'],
        ]);
    }

    #[Route('/api/realtime-data', name: 'channel_analytics_realtime_api')]
    public function realTimeApi(string $slug): JsonResponse
    {
        $channel = $this->channelRepository->findBySlug($slug);
        
        if (!$channel) {
            return new JsonResponse(['error' => 'Канал не найден'], 404);
        }

        if ($channel->getOwner() !== $this->getUser() && !in_array('ROLE_ADMIN', $this->getUser()->getRoles())) {
            return new JsonResponse(['error' => 'Доступ запрещен'], 403);
        }

        $dashboard = $this->analyticsService->getChannelDashboard($channel, 1);

        return new JsonResponse([
            'views' => $dashboard['summary']['totalViews'],
            'subscribers' => $channel->getSubscribersCount(),
            'revenue' => $dashboard['summary']['totalRevenue'],
            'watchTime' => $dashboard['summary']['totalWatchTime'],
            'timestamp' => time(),
        ]);
    }
}