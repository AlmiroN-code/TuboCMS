<?php

namespace App\Repository;

use App\Entity\Season;
use App\Entity\Series;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Season>
 */
class SeasonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Season::class);
    }

    /**
     * @return Season[]
     */
    public function findBySeries(Series $series): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.series = :series')
            ->setParameter('series', $series)
            ->orderBy('s.number', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getMaxNumber(Series $series): int
    {
        $result = $this->createQueryBuilder('s')
            ->select('MAX(s.number)')
            ->where('s.series = :series')
            ->setParameter('series', $series)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? 0;
    }
}
