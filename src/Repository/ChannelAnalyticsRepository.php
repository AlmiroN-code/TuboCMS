<?php

namespace App\Repository;

use App\Entity\Channel;
use App\Entity\ChannelAnalytics;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChannelAnalytics>
 */
class ChannelAnalyticsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChannelAnalytics::class);
    }

    /**
     * Получить аналитику канала за период
     */
    public function findByChannelAndDateRange(Channel $channel, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('ca')
            ->andWhere('ca.channel = :channel')
            ->andWhere('ca.date >= :startDate')
            ->andWhere('ca.date <= :endDate')
            ->setParameter('channel', $channel)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('ca.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Получить аналитику за конкретную дату
     */
    public function findByChannelAndDate(Channel $channel, \DateTimeInterface $date): ?ChannelAnalytics
    {
        return $this->createQueryBuilder('ca')
            ->andWhere('ca.channel = :channel')
            ->andWhere('ca.date = :date')
            ->setParameter('channel', $channel)
            ->setParameter('date', $date->format('Y-m-d'))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Получить суммарную статистику канала за период
     */
    public function getSummaryStats(Channel $channel, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $result = $this->createQueryBuilder('ca')
            ->select([
                'SUM(ca.views) as totalViews',
                'SUM(ca.uniqueViews) as totalUniqueViews',
                'SUM(ca.newSubscribers) as totalNewSubscribers',
                'SUM(ca.unsubscribers) as totalUnsubscribers',
                'SUM(ca.likes) as totalLikes',
                'SUM(ca.comments) as totalComments',
                'SUM(ca.shares) as totalShares',
                'SUM(ca.revenue) as totalRevenue',
                'SUM(ca.watchTimeMinutes) as totalWatchTime',
                'AVG(ca.views) as avgViews',
                'COUNT(ca.id) as daysCount'
            ])
            ->andWhere('ca.channel = :channel')
            ->andWhere('ca.date >= :startDate')
            ->andWhere('ca.date <= :endDate')
            ->setParameter('channel', $channel)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getOneOrNullResult();

        return [
            'totalViews' => (int) ($result['totalViews'] ?? 0),
            'totalUniqueViews' => (int) ($result['totalUniqueViews'] ?? 0),
            'netSubscribers' => (int) ($result['totalNewSubscribers'] ?? 0) - (int) ($result['totalUnsubscribers'] ?? 0),
            'totalNewSubscribers' => (int) ($result['totalNewSubscribers'] ?? 0),
            'totalUnsubscribers' => (int) ($result['totalUnsubscribers'] ?? 0),
            'totalLikes' => (int) ($result['totalLikes'] ?? 0),
            'totalComments' => (int) ($result['totalComments'] ?? 0),
            'totalShares' => (int) ($result['totalShares'] ?? 0),
            'totalRevenue' => (float) ($result['totalRevenue'] ?? 0),
            'totalWatchTime' => (int) ($result['totalWatchTime'] ?? 0),
            'avgViews' => (float) ($result['avgViews'] ?? 0),
            'daysCount' => (int) ($result['daysCount'] ?? 0),
        ];
    }

    /**
     * Получить топ каналы по просмотрам за период
     */
    public function getTopChannelsByViews(\DateTimeInterface $startDate, \DateTimeInterface $endDate, int $limit = 10): array
    {
        return $this->createQueryBuilder('ca')
            ->select([
                'c.id',
                'c.name',
                'c.slug',
                'SUM(ca.views) as totalViews',
                'SUM(ca.revenue) as totalRevenue'
            ])
            ->join('ca.channel', 'c')
            ->andWhere('ca.date >= :startDate')
            ->andWhere('ca.date <= :endDate')
            ->andWhere('c.isActive = :active')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('active', true)
            ->groupBy('c.id')
            ->orderBy('totalViews', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Получить данные для графика просмотров
     */
    public function getViewsChartData(Channel $channel, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $results = $this->createQueryBuilder('ca')
            ->select(['ca.date', 'ca.views', 'ca.uniqueViews'])
            ->andWhere('ca.channel = :channel')
            ->andWhere('ca.date >= :startDate')
            ->andWhere('ca.date <= :endDate')
            ->setParameter('channel', $channel)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('ca.date', 'ASC')
            ->getQuery()
            ->getResult();

        $chartData = [
            'labels' => [],
            'views' => [],
            'uniqueViews' => []
        ];

        foreach ($results as $result) {
            $chartData['labels'][] = $result['date']->format('Y-m-d');
            $chartData['views'][] = $result['views'];
            $chartData['uniqueViews'][] = $result['uniqueViews'];
        }

        return $chartData;
    }

    /**
     * Получить демографические данные канала
     */
    public function getDemographicData(Channel $channel, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $results = $this->createQueryBuilder('ca')
            ->select('ca.demographicData')
            ->andWhere('ca.channel = :channel')
            ->andWhere('ca.date >= :startDate')
            ->andWhere('ca.date <= :endDate')
            ->andWhere('ca.demographicData IS NOT NULL')
            ->setParameter('channel', $channel)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getResult();

        // Агрегируем демографические данные
        $aggregated = [
            'age' => [],
            'gender' => [],
            'country' => []
        ];

        foreach ($results as $result) {
            $data = $result['demographicData'];
            
            if (isset($data['age'])) {
                foreach ($data['age'] as $ageGroup => $count) {
                    $aggregated['age'][$ageGroup] = ($aggregated['age'][$ageGroup] ?? 0) + $count;
                }
            }
            
            if (isset($data['gender'])) {
                foreach ($data['gender'] as $gender => $count) {
                    $aggregated['gender'][$gender] = ($aggregated['gender'][$gender] ?? 0) + $count;
                }
            }
            
            if (isset($data['country'])) {
                foreach ($data['country'] as $country => $count) {
                    $aggregated['country'][$country] = ($aggregated['country'][$country] ?? 0) + $count;
                }
            }
        }

        return $aggregated;
    }

    /**
     * Создать или обновить запись аналитики
     */
    public function createOrUpdateAnalytics(Channel $channel, \DateTimeInterface $date, array $data): ChannelAnalytics
    {
        $analytics = $this->findByChannelAndDate($channel, $date);
        
        if (!$analytics) {
            $analytics = new ChannelAnalytics();
            $analytics->setChannel($channel);
            $analytics->setDate($date);
        }

        // Обновляем данные
        if (isset($data['views'])) {
            $analytics->setViews($analytics->getViews() + $data['views']);
        }
        if (isset($data['uniqueViews'])) {
            $analytics->setUniqueViews($analytics->getUniqueViews() + $data['uniqueViews']);
        }
        if (isset($data['newSubscribers'])) {
            $analytics->setNewSubscribers($analytics->getNewSubscribers() + $data['newSubscribers']);
        }
        if (isset($data['unsubscribers'])) {
            $analytics->setUnsubscribers($analytics->getUnsubscribers() + $data['unsubscribers']);
        }
        if (isset($data['likes'])) {
            $analytics->setLikes($analytics->getLikes() + $data['likes']);
        }
        if (isset($data['comments'])) {
            $analytics->setComments($analytics->getComments() + $data['comments']);
        }
        if (isset($data['shares'])) {
            $analytics->setShares($analytics->getShares() + $data['shares']);
        }
        if (isset($data['revenue'])) {
            $analytics->setRevenue((string) ((float) $analytics->getRevenue() + (float) $data['revenue']));
        }
        if (isset($data['watchTimeMinutes'])) {
            $analytics->setWatchTimeMinutes($analytics->getWatchTimeMinutes() + $data['watchTimeMinutes']);
        }
        if (isset($data['demographicData'])) {
            $analytics->setDemographicData($data['demographicData']);
        }
        if (isset($data['trafficSources'])) {
            $analytics->setTrafficSources($data['trafficSources']);
        }

        $this->getEntityManager()->persist($analytics);
        $this->getEntityManager()->flush();

        return $analytics;
    }
}