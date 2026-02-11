<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\StatsService;
use App\Service\ChannelAnalyticsService;
use App\Service\SystemMonitoringService;
use App\Repository\VideoRepository;
use App\Repository\UserRepository;
use App\Repository\CategoryRepository;
use App\Repository\ModelProfileRepository;
use App\Repository\VideoViewRepository;
use App\Repository\ChannelRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/analytics')]
#[IsGranted('ROLE_ADMIN')]
class AdminAnalyticsController extends AbstractController
{
    public function __construct(
        private readonly StatsService $statsService,
        private readonly SystemMonitoringService $monitoringService,
        private readonly VideoRepository $videoRepository,
        private readonly UserRepository $userRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly ModelProfileRepository $modelRepository,
        private readonly VideoViewRepository $videoViewRepository,
        private readonly ChannelRepository $channelRepository,
    ) {}

    #[Route('', name: 'admin_analytics')]
    public function index(Request $request): Response
    {
        $period = $request->query->getInt('period', 30);
        $compareWith = $request->query->getInt('compare', 0);
        
        // Основная статистика
        $stats = $this->statsService->getDashboardStats();
        
        // Сравнение периодов
        $comparison = null;
        if ($compareWith > 0) {
            $comparison = $this->getComparisonData($period, $compareWith);
        }
        
        // Топ видео
        $topVideos = $this->getTopVideos($period);
        
        // Топ категорий
        $topCategories = $this->getTopCategories($period);
        
        // Топ моделей
        $topModels = $this->getTopModels($period);
        
        // Топ каналов
        $topChannels = $this->getTopChannels($period);
        
        // Статистика по дням
        $dailyStats = $this->getDailyStats($period);
        
        // Воронка конверсии
        $conversionFunnel = $this->getConversionFunnel($period);
        
        // Дополнительная аналитика
        $topTags = $this->getTopTags($period);
        $engagementMetrics = $this->getEngagementMetrics($period);
        $durationDistribution = $this->getVideoDurationDistribution();
        $statusStats = $this->getVideoStatusStats();
        $geoData = $this->getGeographyData($period);
        
        return $this->render('admin/analytics/index.html.twig', [
            'stats' => $stats,
            'period' => $period,
            'compareWith' => $compareWith,
            'comparison' => $comparison,
            'topVideos' => $topVideos,
            'topCategories' => $topCategories,
            'topModels' => $topModels,
            'topChannels' => $topChannels,
            'dailyStats' => $dailyStats,
            'conversionFunnel' => $conversionFunnel,
            'topTags' => $topTags,
            'engagementMetrics' => $engagementMetrics,
            'durationDistribution' => $durationDistribution,
            'statusStats' => $statusStats,
            'geoData' => $geoData,
        ]);
    }

    private function getComparisonData(int $currentPeriod, int $previousPeriod): array
    {
        $currentStart = new \DateTime("-{$currentPeriod} days");
        $previousStart = new \DateTime("-" . ($currentPeriod + $previousPeriod) . " days");
        $previousEnd = new \DateTime("-{$currentPeriod} days");
        
        $current = [
            'videos' => $this->videoRepository->countByDateRange($currentStart, new \DateTime()),
            'users' => $this->userRepository->countByDateRange($currentStart, new \DateTime()),
            'views' => $this->videoRepository->sumViewsByDateRange($currentStart, new \DateTime()),
        ];
        
        $previous = [
            'videos' => $this->videoRepository->countByDateRange($previousStart, $previousEnd),
            'users' => $this->userRepository->countByDateRange($previousStart, $previousEnd),
            'views' => $this->videoRepository->sumViewsByDateRange($previousStart, $previousEnd),
        ];
        
        return [
            'current' => $current,
            'previous' => $previous,
            'changes' => [
                'videos' => $this->calculatePercentChange($previous['videos'], $current['videos']),
                'users' => $this->calculatePercentChange($previous['users'], $current['users']),
                'views' => $this->calculatePercentChange($previous['views'], $current['views']),
            ],
        ];
    }

    private function calculatePercentChange(int $old, int $new): float
    {
        if ($old === 0) {
            return $new > 0 ? 100.0 : 0.0;
        }
        
        return round((($new - $old) / $old) * 100, 1);
    }

    private function getTopVideos(int $days): array
    {
        $startDate = new \DateTime("-{$days} days");
        
        return $this->videoRepository->createQueryBuilder('v')
            ->where('v.createdAt >= :startDate')
            ->andWhere('v.status = :status')
            ->setParameter('startDate', $startDate)
            ->setParameter('status', 'published')
            ->orderBy('v.viewsCount', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    private function getTopCategories(int $days): array
    {
        $startDate = new \DateTime("-{$days} days");
        
        return $this->categoryRepository->createQueryBuilder('c')
            ->select('c.id, c.name, c.slug, COUNT(v.id) as videoCount, SUM(v.viewsCount) as totalViews')
            ->leftJoin('c.videos', 'v')
            ->where('v.createdAt >= :startDate')
            ->andWhere('v.status = :status')
            ->setParameter('startDate', $startDate)
            ->setParameter('status', 'published')
            ->groupBy('c.id')
            ->orderBy('totalViews', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    private function getTopModels(int $days): array
    {
        $startDate = new \DateTime("-{$days} days");
        
        return $this->modelRepository->createQueryBuilder('m')
            ->select('m.id, m.displayName, m.slug, SUM(v.viewsCount) as totalViews, COUNT(v.id) as videoCount')
            ->leftJoin('m.videos', 'v')
            ->where('v.createdAt >= :startDate')
            ->andWhere('v.status = :status')
            ->setParameter('startDate', $startDate)
            ->setParameter('status', 'published')
            ->groupBy('m.id')
            ->orderBy('totalViews', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    private function getTopChannels(int $days): array
    {
        $startDate = new \DateTime("-{$days} days");
        
        return $this->channelRepository->createQueryBuilder('c')
            ->select('c.id, c.name, c.slug, c.subscribersCount, COUNT(v.id) as videoCount, SUM(v.viewsCount) as totalViews')
            ->leftJoin('c.videos', 'v')
            ->where('v.createdAt >= :startDate')
            ->andWhere('v.status = :status')
            ->setParameter('startDate', $startDate)
            ->setParameter('status', 'published')
            ->groupBy('c.id')
            ->orderBy('totalViews', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    private function getDailyStats(int $days): array
    {
        $stats = [];
        $startDate = new \DateTime("-{$days} days");
        
        for ($i = 0; $i < $days; $i++) {
            $date = (clone $startDate)->modify("+{$i} days");
            $nextDate = (clone $date)->modify('+1 day');
            
            $stats[] = [
                'date' => $date->format('Y-m-d'),
                'videos' => $this->videoRepository->countByDateRange($date, $nextDate),
                'users' => $this->userRepository->countByDateRange($date, $nextDate),
                'views' => $this->videoRepository->sumViewsByDateRange($date, $nextDate),
            ];
        }
        
        return $stats;
    }

    private function getConversionFunnel(int $days): array
    {
        $startDate = new \DateTime("-{$days} days");
        
        $totalVideos = $this->videoRepository->count([
            'status' => 'published',
        ]);
        
        $videosWithViews = $this->videoRepository->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.status = :status')
            ->andWhere('v.viewsCount > 0')
            ->andWhere('v.createdAt >= :startDate')
            ->setParameter('status', 'published')
            ->setParameter('startDate', $startDate)
            ->getQuery()
            ->getSingleScalarResult();
        
        $videosWithLikes = $this->videoRepository->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.status = :status')
            ->andWhere('v.likesCount > 0')
            ->andWhere('v.createdAt >= :startDate')
            ->setParameter('status', 'published')
            ->setParameter('startDate', $startDate)
            ->getQuery()
            ->getSingleScalarResult();
        
        return [
            'uploaded' => $totalVideos,
            'viewed' => (int) $videosWithViews,
            'liked' => (int) $videosWithLikes,
        ];
    }





    /**
     * Топ тегов по просмотрам
     */
    private function getTopTags(int $days): array
    {
        $startDate = new \DateTime("-{$days} days");
        
        $conn = $this->videoRepository->getEntityManager()->getConnection();
        
        $sql = "
            SELECT t.id, t.name, t.slug, 
                   COUNT(DISTINCT v.id) as videoCount, 
                   SUM(v.views_count) as totalViews
            FROM tag t
            INNER JOIN video_tag vt ON t.id = vt.tag_id
            INNER JOIN video v ON vt.video_id = v.id
            WHERE v.created_at >= :startDate
              AND v.status = 'published'
            GROUP BY t.id
            ORDER BY totalViews DESC
            LIMIT 10
        ";
        
        $result = $conn->executeQuery($sql, [
            'startDate' => $startDate->format('Y-m-d H:i:s')
        ]);
        
        return $result->fetchAllAssociative();
    }

    /**
     * Метрики вовлеченности (CTR, среднее время просмотра)
     */
    private function getEngagementMetrics(int $days): array
    {
        $startDate = new \DateTime("-{$days} days");
        
        // Средний CTR (views / impressions)
        $result = $this->videoRepository->createQueryBuilder('v')
            ->select('
                SUM(v.viewsCount) as totalViews,
                SUM(v.impressionsCount) as totalImpressions,
                AVG(v.likesCount) as avgLikes,
                AVG(v.commentsCount) as avgComments
            ')
            ->where('v.createdAt >= :startDate')
            ->andWhere('v.status = :status')
            ->setParameter('startDate', $startDate)
            ->setParameter('status', 'published')
            ->getQuery()
            ->getSingleResult();
        
        $totalViews = (float) ($result['totalViews'] ?? 0);
        $totalImpressions = (float) ($result['totalImpressions'] ?? 0);
        $avgLikes = (float) ($result['avgLikes'] ?? 0);
        $avgComments = (float) ($result['avgComments'] ?? 0);
        
        $ctr = $totalImpressions > 0 
            ? ($totalViews / $totalImpressions * 100) 
            : 0;
        
        return [
            'ctr' => round($ctr, 2),
            'avgLikes' => round($avgLikes, 1),
            'avgComments' => round($avgComments, 1),
            'totalViews' => (int) $totalViews,
            'totalImpressions' => (int) $totalImpressions,
        ];
    }

    /**
     * Распределение видео по длительности
     */
    private function getVideoDurationDistribution(): array
    {
        $result = $this->videoRepository->createQueryBuilder('v')
            ->select('
                SUM(CASE WHEN v.duration < 300 THEN 1 ELSE 0 END) as short,
                SUM(CASE WHEN v.duration >= 300 AND v.duration < 1200 THEN 1 ELSE 0 END) as medium,
                SUM(CASE WHEN v.duration >= 1200 THEN 1 ELSE 0 END) as long
            ')
            ->where('v.status = :status')
            ->setParameter('status', 'published')
            ->getQuery()
            ->getSingleResult();
        
        return [
            'short' => (int) $result['short'],   // < 5 мин
            'medium' => (int) $result['medium'], // 5-20 мин
            'long' => (int) $result['long'],     // > 20 мин
        ];
    }

    /**
     * Статистика по статусам видео
     */
    private function getVideoStatusStats(): array
    {
        $conn = $this->videoRepository->getEntityManager()->getConnection();
        
        $sql = "
            SELECT status, COUNT(*) as count
            FROM video
            GROUP BY status
        ";
        
        $result = $conn->executeQuery($sql);
        $stats = [];
        
        foreach ($result->fetchAllAssociative() as $row) {
            $stats[$row['status']] = (int) $row['count'];
        }
        
        return $stats;
    }

    /**
     * География просмотров по странам из video_view
     */
    private function getGeographyData(int $days): array
    {
        $startDate = new \DateTime("-{$days} days");
        
        return $this->videoViewRepository->getGeographyData($startDate);
    }
}
