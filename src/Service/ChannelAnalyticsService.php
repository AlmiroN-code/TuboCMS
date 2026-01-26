<?php

namespace App\Service;

use App\Entity\Channel;
use App\Entity\ChannelAnalytics;
use App\Entity\User;
use App\Repository\ChannelAnalyticsRepository;
use App\Repository\ChannelDonationRepository;
use App\Repository\ChannelSubscriptionRepository;
use App\Repository\VideoRepository;
use Doctrine\ORM\EntityManagerInterface;

class ChannelAnalyticsService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ChannelAnalyticsRepository $analyticsRepository,
        private ChannelSubscriptionRepository $subscriptionRepository,
        private ChannelDonationRepository $donationRepository,
        private VideoRepository $videoRepository
    ) {}

    /**
     * Записать событие просмотра
     */
    public function recordView(Channel $channel, ?User $user = null, array $metadata = []): void
    {
        $today = new \DateTime('today');
        
        $data = [
            'views' => 1,
            'uniqueViews' => $user ? 1 : 0, // Только для авторизованных пользователей
        ];

        // Добавляем демографические данные если есть пользователь
        if ($user) {
            $demographicData = $this->getUserDemographicData($user);
            if ($demographicData) {
                $data['demographicData'] = $demographicData;
            }
        }

        // Добавляем источник трафика
        if (isset($metadata['referrer'])) {
            $data['trafficSources'] = [$this->categorizeTrafficSource($metadata['referrer']) => 1];
        }

        $this->analyticsRepository->createOrUpdateAnalytics($channel, $today, $data);
    }

    /**
     * Записать событие подписки
     */
    public function recordSubscription(Channel $channel, User $user, bool $isNew = true): void
    {
        $today = new \DateTime('today');
        
        $data = $isNew ? ['newSubscribers' => 1] : ['unsubscribers' => 1];
        
        $this->analyticsRepository->createOrUpdateAnalytics($channel, $today, $data);
    }

    /**
     * Записать событие лайка
     */
    public function recordLike(Channel $channel): void
    {
        $today = new \DateTime('today');
        
        $data = ['likes' => 1];
        
        $this->analyticsRepository->createOrUpdateAnalytics($channel, $today, $data);
    }

    /**
     * Записать событие комментария
     */
    public function recordComment(Channel $channel): void
    {
        $today = new \DateTime('today');
        
        $data = ['comments' => 1];
        
        $this->analyticsRepository->createOrUpdateAnalytics($channel, $today, $data);
    }

    /**
     * Записать событие поделиться
     */
    public function recordShare(Channel $channel): void
    {
        $today = new \DateTime('today');
        
        $data = ['shares' => 1];
        
        $this->analyticsRepository->createOrUpdateAnalytics($channel, $today, $data);
    }

    /**
     * Записать доход
     */
    public function recordRevenue(Channel $channel, float $amount): void
    {
        $today = new \DateTime('today');
        
        $data = ['revenue' => $amount];
        
        $this->analyticsRepository->createOrUpdateAnalytics($channel, $today, $data);
    }

    /**
     * Записать время просмотра
     */
    public function recordWatchTime(Channel $channel, int $minutes): void
    {
        $today = new \DateTime('today');
        
        $data = ['watchTimeMinutes' => $minutes];
        
        $this->analyticsRepository->createOrUpdateAnalytics($channel, $today, $data);
    }

    /**
     * Получить дашборд аналитики канала
     */
    public function getChannelDashboard(Channel $channel, int $days = 30): array
    {
        $endDate = new \DateTime('today');
        $startDate = (clone $endDate)->modify("-{$days} days");

        // Основная статистика
        $summaryStats = $this->analyticsRepository->getSummaryStats($channel, $startDate, $endDate);
        
        // Данные для графиков
        $viewsChart = $this->analyticsRepository->getViewsChartData($channel, $startDate, $endDate);
        
        // Демографические данные
        $demographics = $this->analyticsRepository->getDemographicData($channel, $startDate, $endDate);
        
        // Статистика донатов
        $donationStats = $this->donationRepository->getChannelDonationStats($channel, $startDate, $endDate);
        
        // Топ донатеры
        $topDonors = $this->donationRepository->getTopDonors($channel, 5, $startDate, $endDate);
        
        // Рост подписчиков
        $subscriberGrowth = $this->getSubscriberGrowth($channel, $days);
        
        // Топ видео
        $topVideos = $this->getTopVideos($channel, $days);

        return [
            'period' => [
                'days' => $days,
                'startDate' => $startDate,
                'endDate' => $endDate,
            ],
            'summary' => $summaryStats,
            'charts' => [
                'views' => $viewsChart,
                'subscribers' => $subscriberGrowth,
            ],
            'demographics' => $demographics,
            'donations' => [
                'stats' => $donationStats,
                'topDonors' => $topDonors,
            ],
            'topVideos' => $topVideos,
        ];
    }

    /**
     * Получить сравнительную аналитику
     */
    public function getComparativeAnalytics(Channel $channel, int $currentPeriodDays = 30): array
    {
        $currentEndDate = new \DateTime('today');
        $currentStartDate = (clone $currentEndDate)->modify("-{$currentPeriodDays} days");
        
        $previousEndDate = (clone $currentStartDate)->modify('-1 day');
        $previousStartDate = (clone $previousEndDate)->modify("-{$currentPeriodDays} days");

        $currentStats = $this->analyticsRepository->getSummaryStats($channel, $currentStartDate, $currentEndDate);
        $previousStats = $this->analyticsRepository->getSummaryStats($channel, $previousStartDate, $previousEndDate);

        $comparison = [];
        foreach ($currentStats as $key => $currentValue) {
            $previousValue = $previousStats[$key] ?? 0;
            
            if ($previousValue > 0) {
                $change = (($currentValue - $previousValue) / $previousValue) * 100;
            } else {
                $change = $currentValue > 0 ? 100 : 0;
            }
            
            $comparison[$key] = [
                'current' => $currentValue,
                'previous' => $previousValue,
                'change' => round($change, 2),
                'trend' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'stable'),
            ];
        }

        return $comparison;
    }

    /**
     * Получить рост подписчиков
     */
    private function getSubscriberGrowth(Channel $channel, int $days): array
    {
        $endDate = new \DateTime('today');
        $startDate = (clone $endDate)->modify("-{$days} days");

        $analytics = $this->analyticsRepository->findByChannelAndDateRange($channel, $startDate, $endDate);
        
        $chartData = [
            'labels' => [],
            'newSubscribers' => [],
            'unsubscribers' => [],
            'netGrowth' => [],
        ];

        foreach ($analytics as $analytic) {
            $chartData['labels'][] = $analytic->getDate()->format('Y-m-d');
            $chartData['newSubscribers'][] = $analytic->getNewSubscribers();
            $chartData['unsubscribers'][] = $analytic->getUnsubscribers();
            $chartData['netGrowth'][] = $analytic->getNetSubscribers();
        }

        return $chartData;
    }

    /**
     * Получить топ видео канала
     */
    private function getTopVideos(Channel $channel, int $days): array
    {
        $endDate = new \DateTime('today');
        $startDate = (clone $endDate)->modify("-{$days} days");

        return $this->videoRepository->createQueryBuilder('v')
            ->select(['v.id', 'v.title', 'v.slug', 'v.viewsCount', 'v.likesCount', 'v.commentsCount'])
            ->andWhere('v.channel = :channel')
            ->andWhere('v.status = :status')
            ->andWhere('v.createdAt >= :startDate')
            ->setParameter('channel', $channel)
            ->setParameter('status', 'published')
            ->setParameter('startDate', $startDate)
            ->orderBy('v.viewsCount', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    /**
     * Получить демографические данные пользователя
     */
    private function getUserDemographicData(User $user): ?array
    {
        $data = [];

        // Возрастная группа
        if ($user->getBirthDate()) {
            $age = $user->getAge();
            if ($age) {
                if ($age < 18) $ageGroup = '< 18';
                elseif ($age < 25) $ageGroup = '18-24';
                elseif ($age < 35) $ageGroup = '25-34';
                elseif ($age < 45) $ageGroup = '35-44';
                elseif ($age < 55) $ageGroup = '45-54';
                else $ageGroup = '55+';
                
                $data['age'] = [$ageGroup => 1];
            }
        }

        // Пол
        if ($user->getGender()) {
            $data['gender'] = [$user->getGender() => 1];
        }

        // Страна
        if ($user->getCountry()) {
            $data['country'] = [$user->getCountry() => 1];
        }

        return empty($data) ? null : $data;
    }

    /**
     * Категоризировать источник трафика
     */
    private function categorizeTrafficSource(string $referrer): string
    {
        if (empty($referrer) || $referrer === 'direct') {
            return 'direct';
        }

        $domain = parse_url($referrer, PHP_URL_HOST);
        
        if (!$domain) {
            return 'other';
        }

        // Социальные сети
        $socialNetworks = ['facebook.com', 'twitter.com', 'instagram.com', 'tiktok.com', 'reddit.com'];
        foreach ($socialNetworks as $social) {
            if (strpos($domain, $social) !== false) {
                return 'social';
            }
        }

        // Поисковые системы
        $searchEngines = ['google.com', 'bing.com', 'yandex.ru', 'duckduckgo.com'];
        foreach ($searchEngines as $search) {
            if (strpos($domain, $search) !== false) {
                return 'search';
            }
        }

        // Внутренний трафик (тот же домен)
        if (strpos($domain, $_SERVER['HTTP_HOST'] ?? '') !== false) {
            return 'internal';
        }

        return 'referral';
    }

    /**
     * Экспортировать аналитику в CSV
     */
    public function exportAnalytics(Channel $channel, \DateTimeInterface $startDate, \DateTimeInterface $endDate): string
    {
        $analytics = $this->analyticsRepository->findByChannelAndDateRange($channel, $startDate, $endDate);
        
        $csv = "Дата,Просмотры,Уникальные просмотры,Новые подписчики,Отписки,Лайки,Комментарии,Поделились,Доход,Время просмотра (мин)\n";
        
        foreach ($analytics as $analytic) {
            $csv .= sprintf(
                "%s,%d,%d,%d,%d,%d,%d,%d,%.2f,%d\n",
                $analytic->getDate()->format('Y-m-d'),
                $analytic->getViews(),
                $analytic->getUniqueViews(),
                $analytic->getNewSubscribers(),
                $analytic->getUnsubscribers(),
                $analytic->getLikes(),
                $analytic->getComments(),
                $analytic->getShares(),
                (float) $analytic->getRevenue(),
                $analytic->getWatchTimeMinutes()
            );
        }
        
        return $csv;
    }
}