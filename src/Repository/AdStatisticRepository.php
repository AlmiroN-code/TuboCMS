<?php

namespace App\Repository;

use App\Entity\Ad;
use App\Entity\AdStatistic;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AdStatisticRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdStatistic::class);
    }

    public function findOrCreateForToday(Ad $ad): AdStatistic
    {
        $today = new \DateTime('today');
        
        $stat = $this->findOneBy([
            'ad' => $ad,
            'date' => $today,
        ]);

        if (!$stat) {
            $stat = new AdStatistic();
            $stat->setAd($ad);
            $stat->setDate($today);
        }

        return $stat;
    }

    public function findByAdAndDateRange(Ad $ad, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.ad = :ad')
            ->andWhere('s.date >= :startDate')
            ->andWhere('s.date <= :endDate')
            ->setParameter('ad', $ad)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('s.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getAggregatedStats(Ad $ad, int $days = 30): array
    {
        $startDate = new \DateTime("-{$days} days");
        
        return $this->createQueryBuilder('s')
            ->select('
                SUM(s.impressions) as totalImpressions,
                SUM(s.clicks) as totalClicks,
                SUM(s.uniqueImpressions) as totalUniqueImpressions,
                SUM(s.uniqueClicks) as totalUniqueClicks,
                SUM(s.spent) as totalSpent,
                SUM(s.revenue) as totalRevenue,
                SUM(s.conversions) as totalConversions
            ')
            ->where('s.ad = :ad')
            ->andWhere('s.date >= :startDate')
            ->setParameter('ad', $ad)
            ->setParameter('startDate', $startDate)
            ->getQuery()
            ->getSingleResult();
    }

    public function getDailyStats(int $days = 30): array
    {
        $startDate = new \DateTime("-{$days} days");
        
        return $this->createQueryBuilder('s')
            ->select('
                s.date,
                SUM(s.impressions) as impressions,
                SUM(s.clicks) as clicks,
                SUM(s.spent) as spent,
                SUM(s.revenue) as revenue
            ')
            ->where('s.date >= :startDate')
            ->setParameter('startDate', $startDate)
            ->groupBy('s.date')
            ->orderBy('s.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getTopAdsByMetric(string $metric = 'clicks', int $days = 30, int $limit = 10): array
    {
        $startDate = new \DateTime("-{$days} days");
        
        $qb = $this->createQueryBuilder('s')
            ->select('IDENTITY(s.ad) as adId, SUM(s.' . $metric . ') as total')
            ->where('s.date >= :startDate')
            ->setParameter('startDate', $startDate)
            ->groupBy('s.ad')
            ->orderBy('total', 'DESC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    public function getGeoStats(int $days = 30): array
    {
        $startDate = new \DateTime("-{$days} days");
        
        $stats = $this->createQueryBuilder('s')
            ->select('s.geoData')
            ->where('s.date >= :startDate')
            ->andWhere('s.geoData IS NOT NULL')
            ->setParameter('startDate', $startDate)
            ->getQuery()
            ->getResult();

        $aggregated = [];
        foreach ($stats as $stat) {
            if (!empty($stat['geoData'])) {
                foreach ($stat['geoData'] as $country => $data) {
                    if (!isset($aggregated[$country])) {
                        $aggregated[$country] = ['impressions' => 0, 'clicks' => 0];
                    }
                    $aggregated[$country]['impressions'] += $data['impressions'] ?? 0;
                    $aggregated[$country]['clicks'] += $data['clicks'] ?? 0;
                }
            }
        }

        return $aggregated;
    }
}
