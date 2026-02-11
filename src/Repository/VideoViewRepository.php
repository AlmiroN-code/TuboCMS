<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\VideoView;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VideoView>
 */
class VideoViewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VideoView::class);
    }

    /**
     * Получить географию просмотров за период
     */
    public function getGeographyData(\DateTimeInterface $startDate, ?\DateTimeInterface $endDate = null): array
    {
        $qb = $this->createQueryBuilder('vv')
            ->select('LOWER(vv.countryCode) as country, COUNT(vv.id) as viewCount, COUNT(DISTINCT vv.ipAddress) as uniqueViews')
            ->where('vv.countryCode IS NOT NULL')
            ->andWhere("vv.countryCode != ''")
            ->andWhere('vv.viewedAt >= :startDate')
            ->setParameter('startDate', $startDate)
            ->groupBy('vv.countryCode')
            ->orderBy('viewCount', 'DESC')
            ->setMaxResults(10);

        if ($endDate) {
            $qb->andWhere('vv.viewedAt <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Подсчитать просмотры за период
     */
    public function countByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): int
    {
        return (int) $this->createQueryBuilder('vv')
            ->select('COUNT(vv.id)')
            ->where('vv.viewedAt >= :startDate')
            ->andWhere('vv.viewedAt < :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
